/**
 * The registration page's entry.
 *
 * Its own entry rather than a route inside the site: this page needs none of
 * the scroll machinery, so it carries neither GSAP nor three.js, and a reader
 * who came to register waits for a form rather than for a globe.
 *
 * Nothing loads this at the moment. On 18 November 2026 register/index.html
 * became a forwarding page to the forum's own registration page and stopped
 * referencing this entry, so the form is built nowhere. It is kept whole —
 * entry, page and RegisterForm — because the switch back is one line in the
 * data file and one `<script>` tag here; see lib/register-link.ts.
 */
import { createApp } from 'vue';

import RegisterApp from './RegisterApp.vue';
import '../styles/index.css';

const root = document.getElementById('register');
if (!root) throw new Error('#register is missing from register/index.html');

createApp(RegisterApp).mount(root);
