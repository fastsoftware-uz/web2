<script setup lang="ts">
/**
 * Epilogue — registration, what the forum expects to come of it, who will be
 * in the room, and the contacts.
 *
 * The form here used to be a three-step enquiry wizard of its own, asking who
 * you are, which direction interests you and how to reach you. It is now the
 * registration form, the same component the registration page uses: the forum
 * wants the same nine answers from everybody, and one form asked in two
 * places is a better answer than two forms asking overlapping things. A
 * reader who arrives from a project brings it with them — see RegisterForm.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import ForumMark from '../components/ForumMark.vue';
import RegisterForm from '../components/RegisterForm.vue';
import { MESSAGES, type Lang } from '../lib/register-copy';
import { enquirySubject, eventDates, setLang, tr, useStore } from '../lib/state';

const { t } = useI18n();
const { content, forum, lang } = useStore();

/** The form's own copy follows the language the site is being read in. */
const reg = computed(() => MESSAGES[lang.value as Lang]);

/**
 * The same line the registration page prints, with the dates filled in from
 * the data the chapter already has loaded — here there is a store, so it
 * never has to fall back.
 */
const regDates = computed(() =>
  reg.value.dates.replace('{dates}', eventDates() || reg.value.defaultDates),
);

const org = computed(() => content.value?.organization);
const contact = computed(() => forum.value?.contact);

/**
 * The map, from OpenStreetMap's own embed — no key, no account, and the data is
 * ODbL, which the credit under it satisfies. The coordinates were resolved from
 * the committee's address through Nominatim rather than guessed.
 */
const mapSrc = computed(() => {
  const c = contact.value;
  if (!c) return '';
  const d = 0.004;
  const box = [c.lon - d, c.lat - d / 2, c.lon + d, c.lat + d / 2].map((n) => n.toFixed(5)).join('%2C');
  return `https://www.openstreetmap.org/export/embed.html?bbox=${box}&layer=mapnik&marker=${c.lat}%2C${c.lon}`;
});
const roles = computed(() => forum.value?.participants ?? []);
const outcomes = computed(() => forum.value?.outcomes);
</script>

<template>
  <section id="epilogue" class="chapter stage epilogue">
    <!-- The gold seam where the page gives way to the closing stage.
         Decoration, and announced as nothing. -->
    <div class="frieze frieze--edge frieze--top" aria-hidden="true" />

    <div class="shell">
      <p class="eyebrow chapter-mark">{{ t('chapters.epilogue.n') }}</p>
      <h2>{{ reg.title }}</h2>

      <div class="epilogue__grid">
        <!-- The registration page's form, in the chapter a reader reaches by
             reading rather than by arriving. Same component, same endpoint;
             only the heading level and what sits above it differ. -->
        <RegisterForm
          class="epilogue__register"
          :lang="(lang as Lang)"
          :subject="enquirySubject"
          heading-tag="h3"
        >
          <template #intro>
            <p class="epilogue__regDates">{{ regDates }}</p>
            <p class="epilogue__regLead">{{ reg.lead }}</p>
          </template>
        </RegisterForm>

        <address v-if="contact" class="contacts">
          <p class="contacts__org">
            <small>{{ t('contact.organiser') }}</small>
            {{ tr(contact.organisation) }}
          </p>
          <p>
            <small>{{ t('contact.email') }}</small>
            <a :href="`mailto:${contact.email}`">{{ contact.email }}</a>
          </p>
          <p>
            <small>{{ t('contact.phone') }}</small>
            <a :href="contact.phoneHref">{{ contact.phone }}</a>
            <em>{{ t('contact.shortPhone', { n: contact.shortPhone }) }}</em>
          </p>
          <p>
            <small>{{ t('contact.address') }}</small>
            {{ contact.postcode }}, {{ tr(contact.address) }}
          </p>

          <div class="contacts__map">
            <iframe
              :src="mapSrc"
              :title="t('contact.mapTitle')"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
            />
          </div>
          <p class="contacts__credit">
            <a :href="contact.mapUrl" target="_blank" rel="noopener noreferrer">{{ t('contact.map') }}</a>
            <span>{{ t('contact.mapCredit') }}</span>
          </p>

          <p v-if="org" class="contacts__also">
            <small>{{ t('contact.agency') }}</small>
            <a :href="`mailto:${org.contacts.emails[0]}`">{{ org.contacts.emails[0] }}</a>
            <a :href="org.contacts.phone_href">{{ org.contacts.phone }}</a>
          </p>
        </address>
      </div>

      <!-- What is expected to come of it ----------------------------------- -->
      <div v-if="outcomes" class="outcomes">
        <h3 class="epilogue__sub">{{ t('epilogue.outcomesTitle') }}</h3>
        <div class="outcomes__cols">
          <div>
            <p class="eyebrow">{{ t('epilogue.outcomesQuantitative') }}</p>
            <ul>
              <li v-for="(item, i) in outcomes.quantitative" :key="i">{{ tr(item) }}</li>
            </ul>
          </div>
          <div>
            <p class="eyebrow">{{ t('epilogue.outcomesQualitative') }}</p>
            <ul>
              <li v-for="(item, i) in outcomes.qualitative" :key="i">{{ tr(item) }}</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Who is in the room ------------------------------------------------ -->
      <h3 class="epilogue__sub">{{ t('epilogue.participantsTitle') }}</h3>
      <!-- The names alone. The line under each one, describing which
           organisations fall into the category, came off on 26 October 2026
           together with the rest of the site's explanatory asides; the text
           is untouched in forum-2026.json. A list of names is a <ul>, not a
           description list with nothing described. -->
      <ul class="who">
        <li v-for="role in roles" :key="role.id">{{ tr(role.category) }}</li>
      </ul>

      <footer v-if="content && forum" class="footer">
        <!-- The footer used to carry official links, a numbered source list
             and a concept/demo notice. The client took all three off on
             17 October 2026: this is the organiser's own site now, not a
             rendering of one, and a site does not cite the documents it is
             made of.

             What is left is the one line that is not ours to drop. The region
             outlines and the globe's border are geoBoundaries data under
             ODbL 1.0, whose terms require the attribution to travel with the
             work. It is a plain credit, not a link. Replacing that data with
             a public-domain source would remove even this. -->
        <p class="footer__label">{{ t('credits.contributedBy') }}</p>
        <ul class="footer__contrib">
          <li v-for="(who, i) in forum.portfolio.contributedBy" :key="i">{{ tr(who) }}</li>
        </ul>

        <div class="footer__end">
          <ForumMark form="lockup" variant="white" class="footer__mark" />
          <div class="footer__langs">
            <button v-for="code in ['uz', 'ru', 'en'] as const" :key="code" type="button" @click="setLang(code)">
              {{ code }}
            </button>
          </div>
        </div>

        <p class="footer__fine">{{ t('credits.mapData') }}</p>
      </footer>
    </div>
  </section>
</template>

<style scoped>
.epilogue {
  align-content: start;
  /* 7rem, not 5: the fixed bar is 93px and a chapter that sets its own top
     padding has to clear it at every window height, as .chapter now does. */
  padding-block-start: clamp(7rem, 14vh, 9rem);
}

h2 {
  font-size: clamp(2.25rem, 6vw, 4.5rem);
  margin-block-start: 0.75rem;
}
.epilogue__sub {
  margin-block-start: clamp(3.5rem, 10vh, 6rem);
  font-size: clamp(1.4rem, 3vw, 2.25rem);
}

.epilogue__grid {
  margin-block-start: clamp(2.5rem, 7vh, 4rem);
  display: grid;
  /* minmax(0, …), not the implicit `auto`: an auto column sizes to its widest
     content, and the form inside it has field labels and role cards that do
     not have to fit a phone. Without this the card ran off the right edge. */
  grid-template-columns: minmax(0, 1fr);
  gap: 2rem;
}
@media (min-width: 60rem) {
  .epilogue__grid {
    grid-template-columns: minmax(0, 1fr) minmax(0, 20rem);
  }
}

/* The chapter's own frame for the shared form: a card on the stage, the way
   the wizard it replaced sat. The form brings its own insides. */
.epilogue__register {
  padding: clamp(1.5rem, 4vw, 2.5rem);
  border: 1px solid var(--hairline);
  border-radius: 1rem;
  background: linear-gradient(160deg, var(--raise), transparent 60%);
}
.epilogue__regDates {
  color: var(--gold);
  font-size: 0.95rem;
}
.epilogue__regLead {
  margin-block-start: 0.5rem;
  color: var(--ink-soft);
  max-inline-size: 34rem;
}

.contacts {
  font-style: normal;
  display: grid;
  gap: 1rem;
  align-content: start;
  padding: clamp(1.25rem, 3vw, 1.75rem);
  border: 1px solid var(--hairline);
  border-radius: 1rem;
}
.contacts__org {
  padding-block-end: 0.9rem;
  border-block-end: 1px solid var(--hairline);
  color: var(--ink);
  font-size: 0.95rem;
}
.contacts p em {
  font-style: normal;
  font-size: 0.8rem;
  color: var(--ink-dim);
}

/* The committee's own address on a map. Lazy, so it costs nothing until the
   epilogue is actually reached.
   
   The frame is taller than its box so the embed's own footer bar — a donation
   appeal and a link to the API terms — falls outside it. The attribution that
   ODbL actually requires is the line underneath, which is ours and stays. */
.contacts__map {
  position: relative;
  margin-block-start: 0.5rem;
  block-size: 13rem;
  border: 1px solid var(--hairline);
  border-radius: 0.75rem;
  overflow: hidden;
  background: var(--surface-2);
}
.contacts__map iframe {
  position: absolute;
  inset-block-start: 0;
  inset-inline: 0;
  display: block;
  inline-size: 100%;
  block-size: calc(100% + 5rem);
  border: 0;
  filter: saturate(0.82);
}
.contacts__credit {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.6rem;
  font-size: 0.72rem;
  color: var(--ink-dim);
}
.contacts__also {
  padding-block-start: 0.9rem;
  border-block-start: 1px solid var(--hairline);
}
.contacts__also a {
  font-size: 0.85rem;
}
.contacts p {
  display: grid;
  gap: 0.2rem;
  font-size: 0.92rem;
}
.contacts small {
  font-size: 0.72rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--ink-dim);
}
.contacts a {
  color: var(--ink);
  text-decoration-color: var(--accent-deep);
  text-underline-offset: 3px;
}
/* An address and a telephone number are the two things on this page a reader
   presses to leave it. A line of text is a 24-pixel target; under a thumb
   they get the room of a button without being drawn as one. */
@media (pointer: coarse) {
  .contacts a {
    display: inline-flex;
    align-items: center;
    min-block-size: var(--tap);
  }
}

.outcomes__cols {
  margin-block-start: 1.75rem;
  display: grid;
  gap: 2rem;
}
@media (min-width: 56rem) {
  .outcomes__cols {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 3rem;
  }
}
.outcomes ul {
  margin: 1rem 0 0;
  padding-inline-start: 1.1rem;
  display: grid;
  gap: 0.5rem;
  font-size: 0.92rem;
}
.who {
  margin: 1.75rem 0 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0;
}
@media (min-width: 56rem) {
  .who {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 3rem;
  }
}
.who > li {
  padding-block: 0.85rem;
  border-block-end: 1px solid var(--hairline);
  color: var(--ink);
  font-size: 0.95rem;
}

.footer {
  margin-block-start: clamp(4rem, 12vh, 7rem);
  padding-block-start: clamp(2rem, 5vh, 3rem);
  border-block-start: 1px solid var(--hairline);
}
.footer h3 {
  font-family: var(--font-body);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--ink-dim);
}
.footer ul {
  list-style: none;
  margin: 1rem 0 0;
  padding: 0;
  display: grid;
  gap: 0.5rem;
  font-size: 0.9rem;
}
.footer a {
  color: var(--ink-soft);
  text-decoration-color: var(--hairline);
  text-underline-offset: 3px;
}
/* A column of official links set at reading leading gives each one a 19-pixel
   target. Under a thumb they are set as rows instead — the gap does the work,
   so the type and the underline are untouched. */
@media (pointer: coarse) {
  .footer ul {
    gap: 0;
  }
}
@media (hover: hover) {
  .footer a:hover {
    color: var(--ink);
  }
}
.footer__note {
  margin-block-start: 0.75rem;
  font-size: 0.8rem;
  color: var(--ink-dim);
}
.footer__label {
  margin-block-start: 1.5rem;
  font-size: 0.72rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--ink-dim);
}
/* One line of names rather than a column of them: with the source list and
   the links gone this is the only thing left up here, and a narrow column of
   it left four fifths of the footer empty. */
.footer__contrib {
  list-style: none;
  margin: 0.9rem 0 0;
  padding: 0;
  /* Columns rather than one line or one stack: these are long names, and with
     the source list and the links gone they are the only thing up here — a
     single column left four fifths of the footer empty, and running them on
     stranded a separator at the start of every wrapped line. */
  display: grid;
  /* min(…, 100%) so the track cannot be wider than the page: 18rem is 288px
     and a 280px phone is a real one. */
  grid-template-columns: repeat(auto-fill, minmax(min(18rem, 100%), 1fr));
  gap: 0.5rem 2.5rem;
  max-inline-size: 64rem;
  font-size: 0.82rem;
  color: var(--ink-dim);
}

/* The map data's licence condition, set as the small print it is. */
.footer__fine {
  margin-block-start: 1.5rem;
  max-inline-size: 60rem;
  font-size: 0.72rem;
  line-height: 1.5;
  color: var(--ink-dim);
}

.footer__end {
  margin-block-start: clamp(2.5rem, 6vh, 4rem);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}
/* Wider than it was. The lockup's cap height is now a smaller fraction of its
   width — five lines at one size beside the emblem, rather than three with a
   small qualifier over them — so the same width would set the name a third
   smaller than it used to read here. */
.footer__mark {
  width: clamp(9.5rem, 21vw, 12.5rem);
  opacity: 0.6;
}
.footer__langs {
  display: flex;
  gap: 0.25rem;
}
.footer__langs button {
  padding: 0.4rem 0.75rem;
  border: 1px solid var(--hairline);
  border-radius: 0.2rem;
  background: transparent;
  color: var(--ink-dim);
  font: inherit;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  cursor: pointer;
}
@media (hover: hover) {
  .footer__langs button:hover {
    color: var(--ink);
    border-color: var(--accent);
  }
}
</style>
