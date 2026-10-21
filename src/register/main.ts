/**
 * The registration page's entry.
 *
 * Its own entry rather than a route inside the site: this page needs none of
 * the scroll machinery, so it carries neither GSAP nor three.js, and a reader
 * who came to register waits for a form rather than for a globe.
 */
import { createApp } from 'vue';

import RegisterApp from './RegisterApp.vue';
import '../styles/index.css';

const root = document.getElementById('register');
if (!root) throw new Error('#register is missing from register/index.html');

createApp(RegisterApp).mount(root);
