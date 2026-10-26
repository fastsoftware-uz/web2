/**
 * The admin's only script, and it is here so that there is none in the page.
 *
 * Both behaviours used to be attributes — `onchange="this.form.submit()"` on
 * the status menu and an `onsubmit` confirm on "revert everything". An
 * attribute is an inline script, and an inline script is the one thing a
 * content policy strict enough to be worth sending cannot allow. Moved here,
 * the admin can say `script-src 'self'` and mean it.
 *
 * Delegated from the document, so rows rendered on any page work with no
 * wiring per element.
 */

document.addEventListener('change', (event) => {
  const el = event.target;
  if (el instanceof HTMLSelectElement && el.hasAttribute('data-autosubmit') && el.form) {
    el.form.submit();
  }
});

document.addEventListener(
  'submit',
  (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const question = form.dataset.confirm;
    if (question && !window.confirm(question)) {
      event.preventDefault();
    }
  },
  true,
);
