/**
 * The admin's only script, and it is here so that there is none in the page.
 *
 * Every behaviour below used to be, or would otherwise be, an attribute —
 * `onchange`, `onsubmit`, an inline handler on a button. An attribute is an
 * inline script, and an inline script is the one thing a content policy
 * strict enough to be worth sending cannot allow. Moved here, the admin can
 * say `script-src 'self'` and mean it.
 *
 * Everything is delegated from the document, so a row rendered on any page
 * works with no wiring per element, and nothing here is required for the
 * page to be usable: every one of these is a convenience over a form that
 * already works without it.
 */

/* A menu that submits itself. The noscript button beside it does the same. */
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
      return;
    }

    /*
     * A file bigger than the server will take.
     *
     * nginx refuses an over-sized body before PHP is reached and answers a
     * bare 413, which a form post shows as a blank page with no explanation
     * at all. Catching it here turns that into a sentence, and the upload is
     * never started.
     */
    const limit = Number(form.dataset.limit || 0);
    if (limit > 0) {
      for (const input of form.querySelectorAll('input[type=file]')) {
        for (const file of input.files || []) {
          if (file.size > limit) {
            event.preventDefault();
            window.alert(
              `«${file.name}» hajmi ${(file.size / 1048576).toFixed(1)} MB — serverning ` +
                `chegarasi ${(limit / 1048576).toFixed(0)} MB. Faylni kichraytiring yoki ` +
                `rasmlarni ikki qismga bo‘lib yuboring.`,
            );
            return;
          }
        }
      }
    }
  },
  true,
);

document.addEventListener('click', (event) => {
  const el = event.target;
  if (!(el instanceof HTMLElement)) return;

  /* A smudged check: ask for the same challenge to be drawn again. The
     answer does not move — it lives in the session — so this is a new
     drawing of the same five characters. */
  if (el.hasAttribute('data-captcha-reload')) {
    const img = document.getElementById('capimg');
    if (img instanceof HTMLImageElement) {
      img.src = `captcha.php?r=${Date.now()}`;
      const box = document.getElementById('cap');
      if (box instanceof HTMLInputElement) box.focus();
    }
  }

  /*
   * "Will this server take a file at all?"
   *
   * The body limit lives in nginx, where nobody can see it from here, and
   * the symptom of it being too small is an upload that ends in a blank
   * page. This sends two megabytes and reports what came back, which is the
   * difference between a two-minute fix and an afternoon.
   */
  if (el.hasAttribute('data-probe')) {
    const out = document.querySelector('[data-probe-out]');
    const token = document.querySelector('input[name=token]');
    if (!(out instanceof HTMLElement) || !(token instanceof HTMLInputElement)) return;
    out.textContent = ' tekshirilyapti…';
    const body = new Blob([new Uint8Array(2 * 1024 * 1024)]);
    fetch('import.php?probe=1', {
      method: 'POST',
      body,
      headers: { 'X-Admin-Token': token.value, 'Content-Type': 'application/octet-stream' },
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((data) => {
        out.textContent = ` ✓ server 2 MB qabul qildi (chegara ${Math.round(
          data.limit / 1048576,
        )} MB).`;
      })
      .catch((e) => {
        out.textContent =
          e.message === '413'
            ? ' ✗ nginx 2 MB ni rad etdi — client_max_body_size ni oshirish kerak (server/README.md).'
            : ` ✗ server javob bermadi (${e.message}).`;
      });
  }
});

/*
 * A first password nobody has to invent.
 *
 * The person making an account is between two meetings and the password they
 * would otherwise type is the name of the forum. Filled in rather than
 * forced: the box stays editable, and it is a text box on purpose — it is
 * going to be read out loud to the person it belongs to.
 */
document.addEventListener('DOMContentLoaded', () => {
  for (const box of document.querySelectorAll('input[data-suggest-password]')) {
    if (!(box instanceof HTMLInputElement) || box.value) continue;
    const alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const bytes = new Uint32Array(16);
    crypto.getRandomValues(bytes);
    box.value = Array.from(bytes, (n) => alphabet[n % alphabet.length]).join('');
  }
});
