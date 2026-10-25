# Registration backend and admin

Two things live here: the endpoint the registration page posts to, and the
page the organiser reads the registrations on. Plain PHP, no composer, no
framework, no build step — deploying it is copying this directory.

It is written for the server the client described: **Alpine Linux, nginx,
PHP-FPM running as `php-dev`, an older site already in place.** Nothing here
touches that site; everything is under one prefix you choose.

> The rest of this repository is a frontend-only project and `CLAUDE.md` says
> so. This directory is the exception, added at the client's request on
> 17 October 2026 — the site now needs somewhere for a registration to go.

---

## What it is

```
server/
  public/            ← the only directory nginx is allowed to reach
    api.php          ← POST a registration; GET it for a health check
    index.php        ← the admin: login, list, search, status, CSV
    site.php         ← the editor: the site's own texts, and the projects
    admin.css
  src/
    config.example.php ← copy to config.php and edit
    app.php            ← validation and the record's shape
    storage.php        ← SQLite, or a flat file when SQLite is missing
    site.php           ← the overlay the editor saves into
  data/              ← the registrations and the overlay. NOT under public/.
                       Must be writable.
```

`src/` and `data/` are outside the web root on purpose: if nginx is ever
pointed at the wrong directory, the password hash and the registrations are
still not downloadable.

## Running the whole thing on your own machine

Before any of this is copied anywhere, the chain can be driven end to end
locally — form, endpoint, store, admin, CSV — with PHP 8.1+ on PATH:

```sh
npm run server:password -- "the password you choose"   # writes src/config.php
npm run server                                          # builds site 2, then serves it
```

| | |
|---|---|
| Site | http://127.0.0.1:8787/ |
| Registration | http://127.0.0.1:8787/register/ |
| Admin | http://127.0.0.1:8787/admin/ |
| E-ticket | http://127.0.0.1:8787/ticket/&lt;number&gt; |
| Health | http://127.0.0.1:8787/api/health |

`dev-server.php` is the nginx block below written as a router for `php -S`,
so the addresses are the ones the real deployment serves. It is a development
server and nothing else: single threaded, and never part of a deployment.

`node qa/backend.mjs` runs the same chain unattended, against a throwaway
data directory and a throwaway password, and puts your config.php back
afterwards.

## Storage

**SQLite when `pdo_sqlite` is installed, an append-only JSON-lines file when
it is not.** Alpine ships PHP's extensions as separate apk packages and
`php84-pdo_sqlite` is frequently not among the ones installed, so a backend
that assumes it is a backend that 500s on arrival. Both stores are safe to
write from several PHP-FPM workers at once. `GET /api/health` says which one
you got.

Set `'storage' => 'jsonl'` in the config if you would rather be able to read
the registrations with `cat` and count them with `wc -l` than install an
extension. On a forum's guest list that is a reasonable preference.

---

## The e-ticket

A registration is answered with one, there and then:

```json
{"ok":true,"id":417,"ticket":{"number":"TITF26-000417-K7F3QA",
 "url":"https://forum.example.uz/ticket/TITF26-000417-K7F3QA"}}
```

| | |
|---|---|
| `/ticket/<number>` | the badge: one A4 sheet, four quadrants, folds into a badge with Uzbek on one face and English on the other |
| `/ticket/<number>?scan=1` | what the QR code opens: name, role and one line saying whether to admit the holder |

**The number is the credential.** It is the registration's id with six
characters of HMAC after it, keyed from `data/ticket-secret.key`, which is
created on first use and is the one thing that must be backed up with the
registrations — delete it and every ticket issued so far stops opening. The id
alone is a counter, so without the signature every guest's badge would be one
URL away from every other; with it, a guessed address is one of a billion. It
is not a password: whoever holds the ticket can open it, which is the point of
sending it to them. The page shows only what a badge shows — never the e-mail
address, the telephone number or the note.

**Issued on registration, approved in the admin.** The guest leaves the form
with the badge in their hand; the badge carries the status, so a registration
the organisers decline says `BEKOR QILINGAN · CANCELLED` on its own face and
the scan view turns red. Nothing has to be re-sent.

**The QR code is drawn by PHP**, in `src/qr.php` — byte mode, level M,
versions 1 to 10, as SVG. No composer, no GD, and nothing that depends on
JavaScript: a badge whose barcode needs a script is blank when it is printed
from a saved file. `node qa/ticket.mjs` renders what it draws and reads it
back with a real decoder.

`site_url` in the configuration is the address that goes inside the QR code.
Empty means "the host this request came in on", which is right everywhere
except behind a proxy that rewrites it.

---

## Install

### 1. Packages

```sh
apk add nginx php84 php84-fpm php84-session php84-json php84-mbstring php84-opcache
# Optional but recommended — without it the flat file is used instead:
apk add php84-pdo php84-pdo_sqlite
```

### 2. Files

```sh
install -d -o php-dev -g www-data /var/www/forum
cp -r server /var/www/forum/server          # this directory
cp -r site-2-silkroad/dist/. /var/www/forum/public   # the built site

install -d -o php-dev -g php-dev -m 750 /var/www/forum/server/data
```

`/var/www/forum/public` holds `index.html`, `register/index.html` and
`assets/` — the output of `npm run build:2`. The registration page is a
directory rather than a `.html` file so that its address reads
`/register/`; `try_files $uri $uri/` below is what serves it.

### 3. Configure

```sh
cd /var/www/forum/server/src
cp config.example.php config.php
php -r 'echo password_hash("the password you chose", PASSWORD_DEFAULT), "\n";'
```

Paste the hash into `admin_password_hash` in `config.php`. Leave
`allowed_origins` empty if nginx serves the site and the API from the same
host, which is what the nginx block below does.

```sh
chown php-dev:php-dev config.php && chmod 640 config.php
```

### 3b. The site editor

Set `site_dir` in `config.php` to the directory nginx serves the site from:

```php
'site_dir' => '/var/www/forum/public',
```

The admin publishes by rewriting `data/forum-2026.json` and `data/content.json`
inside it — those are the two files the page fetches at runtime, so an edit is
live the moment it is saved. PHP-FPM therefore has to be able to write that one
directory:

```sh
chown -R php-dev:php-dev /var/www/forum/public/data
chmod 750 /var/www/forum/public/data
```

Nothing else in the web root needs to be writable, and nothing is. If this step
is skipped the registrations still work and only the editor reports that it
cannot save, which is the right way round.

**It edits the site on this server and nowhere else.** While the published site
is still on GitHub Pages, what the organiser changes here will not show there:
the editor changes the copy nginx serves. See *While the site is still on
GitHub Pages* below.

The overlay itself — what was changed, and what it was before — lives in
`server/data/site-*.json`, outside the web root with the registrations.

### 4. nginx

```nginx
server {
    listen 80;
    server_name forum.example.uz;

    root /var/www/forum/public;
    index index.html;

    # The built site. Everything is a real file; there is no SPA router to
    # fall back for, so a miss is a miss.
    location / {
        try_files $uri $uri/ =404;
    }

    # The hashed assets never change under their own name.
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # The e-ticket. The number is in the path; ticket.php reads it from
    # REQUEST_URI, so there is nothing to capture here.
    location ^~ /ticket/ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm84/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/forum/server/public/ticket.php;
    }

    # The one public endpoint.
    location = /api/register {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm84/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/forum/server/public/api.php;
    }

    location = /api/health {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm84/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/forum/server/public/api.php;
        fastcgi_param REQUEST_METHOD GET;
    }

    # The admin. Add `allow`/`deny` here if it should only open from the
    # office; the password is the lock, this would be the second one.
    location /admin/ {
        alias /var/www/forum/server/public/;
        index index.php;

        location ~ ^/admin/(.+\.php)$ {
            include fastcgi_params;
            fastcgi_pass unix:/run/php-fpm84/php-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/forum/server/public/$1;
        }
    }

    # src/ and data/ are not under root, so there is nothing to deny here —
    # but if the layout is ever changed, deny them before anything else.
}
```

```sh
rc-update add php-fpm84 && rc-service php-fpm84 start
rc-update add nginx && rc-service nginx restart
```

### 5. Check it

```sh
curl -s https://forum.example.uz/api/health
# {"ok":true,"store":"SQLite (pdo_sqlite)","detail":"ok"}
```

Then open `https://forum.example.uz/admin/` and log in.

---

## While the site is still on GitHub Pages

The published site is served from `*.github.io` and this API is on the
organiser's server, so the browser treats the POST as cross-origin. Two
changes:

1. In `config.php`:
   ```php
   'allowed_origins' => ['https://fastsoftware-uz.github.io'],
   ```
   A request from any other origin is refused, so that list is the whole of
   the guest list.

2. Build the site with the absolute endpoint:
   ```sh
   VITE_REGISTER_ENDPOINT=https://forum.example.uz/api/register npm run build:2
   ```

   The Pages repository builds itself, so set it there instead of rebuilding
   by hand: **Settings → Secrets and variables → Actions → Variables → New
   variable**, named `REGISTER_ENDPOINT`, with that same URL as its value.
   The deploy workflow passes it to the build, and the next push picks it up.

Until that variable is set the POST goes nowhere, and the page says so and
offers the organiser's address with the answers already filled into the
message — a registration is not lost in the meantime, it just arrives by
email.

When the site moves onto this server, empty the list and drop the variable:
same-origin needs neither.

---

## The API

### `POST /api/register`

```json
{ "role": "guest|speaker", "full_name": "", "organisation": "", "position": "",
  "country": "", "email": "", "phone": "", "direction": "", "note": "", "lang": "uz" }
```

`role`, `full_name`, `organisation`, `position`, `country`, `email` and
`phone` are required. `direction` must be one of the six the site shows or it
is dropped. A form POST is accepted as well as JSON.

| Status | Body | Means |
|---|---|---|
| 200 | `{"ok":true,"id":12}` | Stored. `id` is the reference the visitor is shown. |
| 422 | `{"ok":false,"fields":["email"]}` | Named fields are wrong. No prose: the page knows the reader's language and this does not. |
| 429 | `{"ok":false,"error":"rate"}` | More than `rate_limit_per_hour` from one address. |
| 403 | `{"ok":false,"error":"origin"}` | Cross-origin, and the origin is not in the list. |
| 500 | `{"ok":false,"error":"store"}` | Could not write. The reason is in the PHP error log, not in the response. |

### `GET /api/health`

`{"ok":true,"store":"…","detail":"ok"}` — the quickest way to tell from a
browser that nginx, PHP and the data directory are all talking to each other.

---

## What is guarded, and what is not

**Guarded.** The admin is behind `password_hash`/`password_verify` with a
fixed delay on a wrong answer and a fresh session id on a right one. Status
changes carry a CSRF token. The session cookie is `HttpOnly`, `SameSite=Strict`,
and `Secure` whenever the request arrived over HTTPS. Every value that reaches
the page goes through `htmlspecialchars`, and SQLite is only ever given bound
parameters. The form carries a honeypot field, and a bot that fills it is
answered `200` and stored nowhere — told it had failed, it would come back
having learned something. One address may send `rate_limit_per_hour`
registrations an hour.

**Not guarded, deliberately.** There is no email confirmation: a registration
is a lead for the organising committee to call, not an account. There is no
rate limit on admin logins beyond the delay — put the admin behind an nginx
`allow`/`deny` if it needs more. There is no backup; `data/` is one directory
and belongs in whatever already backs that server up.

**Please also:**

- Serve this over HTTPS. The admin password crosses the wire on every login.
- Set `display_errors = Off` in production. A PHP notice printed into a
  response lands inside the CSV and corrupts it — that is not a theory, it
  happened while this was being written, and the fix is in the code as well.
- Back `data/` up. It is the only copy of who is coming.
