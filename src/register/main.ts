/**
 * The registration page's entry.
 *
 * Its own entry rather than a route inside the site: this page needs none of
 * the scroll machinery, so it carries neither GSAP nor three.js, and a reader
 * who came to register waits for a form rather than for a globe.
 *
 * For a day, 18 to 19 November 2026, register/index.html was a forwarding
 * page and did not load this at all. It does again. The page now forwards
 * from inside the app instead, when the data file says registration has
 * moved — see RegisterApp.vue and lib/register-link.ts.
 */
import { createApp } from 'vue';

import RegisterApp from './RegisterApp.vue';
import '../styles/index.css';

const root = document.getElementById('register');
if (!root) throw new Error('#register is missing from register/index.html');

createApp(RegisterApp).mount(root);
