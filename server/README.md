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

**One file configures an installation: `server/.env`.** Copy
`.env.example` to it, fill it in, and nothing else on the server is edited by
hand. **One command says whether it will work: `php server/bin/doctor.php`.**

---

## What it is

```
server/
  .env.example       ← copy to .env and fill in. The only file you edit.
  public/            ← the only directory nginx is allowed to reach
    api.php          ← POST a registration; GET it for a health check
    index.php        ← the admin: login, list, search, status, password, CSV
    site.php         ← the editor: the site's own texts, and the projects
    admin.css  admin.js
  src/
    env.php            ← reads .env; the whole configuration is assembled here
    guard.php          ← sessions, headers, the login throttle, the password
    app.php            ← validation and the record's shape
    storage.php        ← SQLite, or a flat file when SQLite is missing
    site.php           ← the overlay the editor saves into
    ticket.php  qr.php ← the e-ticket and the QR code it carries
  bin/
    doctor.php       ← why it is not working. Run this first.
    password.php     ← set the admin password
    publish.php      ← re-apply the admin's edits after a deploy
  data/              ← the registrations, the overlay, the ticket key, the
                       password if it was changed from the admin.
                       NOT under public/. Must be writable.
```

`src/`, `bin/` and `data/` are outside the web root on purpose: if nginx is
ever pointed at the wrong directory, the password hash and the registrations
are still not downloadable. `.env` is outside it for the same reason, and the
nginx block below refuses dotfiles as well — two locks on one door, because
this is the file that would matter.

## Running the whole thing on your own machine

Before any of this is copied anywhere, the chain can be driven end to end
locally — form, endpoint, store, admin, CSV — with PHP 8.1+ on PATH:

```sh
npm run server:password -- "the password you choose"   # writes server/.env
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

`node qa/backend.mjs` runs the same chain unattended — twenty-seven checks,
against a throwaway data directory, a throwaway password and its own `.env`.
It never touches a real installation's configuration.

## Storage

**SQLite when `pdo_sqlite` is installed, an append-only JSON-lines file when
it is not.** Alpine ships PHP's extensions as separate apk packages and
`php84-pdo_sqlite` is frequently not among the ones installed, so a backend
that assumes it is a backend that 500s on arrival. Both stores are safe to
write from several PHP-FPM workers at once. `GET /api/health` says which one
you got.

Set `STORAGE=jsonl` in `.env` if you would rather be able to read the
registrations with `cat` and count them with `wc -l` than install an
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
address, the telephone number or the note — and it is sent with `no-store`, so
it is not left behind in a browser's cache on a shared machine.

**Issued on registration for two of the four roles.** A visitor and an
investor register themselves and leave the form with the badge in their hand.
A VIP and a speaker are invited rather than registered — their attendance is
arranged by hand — so their badge waits: the address answers and says it is
not issued yet, and one click in the admin releases it. The list and the rule
are `ROLES` and `TICKET_AUTO` in `src/app.php`; what has been released by hand
is a list of ids in `data/tickets-released.json`.

**Approval stays with the organisers.** The badge carries the status, so a
registration they decline says `BEKOR QILINGAN · CANCELLED` on its own face
and the scan view turns red. Nothing has to be re-sent.

**The QR code is drawn by PHP**, in `src/qr.php` — byte mode, level M,
versions 1 to 10, as SVG. No composer, no GD, and nothing that depends on
JavaScript: a badge whose barcode needs a script is blank when it is printed
from a saved file. `node qa/ticket.mjs` renders what it draws and reads it
back with a real decoder.

`SITE_URL` in `.env` is the address that goes inside the QR code. Empty means
"the host this request came in on", which is right everywhere except behind a
proxy that rewrites it.

---

## Install

### 1. Packages

```sh
apk add nginx php84 php84-fpm php84-session php84-json php84-mbstring php84-opcache
# Optional but recommended — without it the flat file is used instead:
apk add php84-pdo php84-pdo_sqlite
# For the automatic deploy below:
apk add rsync openssh
```

### 2. A group both users are in

PHP-FPM writes the registrations; the deploy writes the files. They are two
different users and they share two directories, so they share a group — and
the group sticks to anything created later, which is what `2770` buys:

```sh
addgroup forum
adduser php-dev forum          # the PHP-FPM user
adduser deploy                 # the user the deploy logs in as
adduser deploy forum

install -d -o deploy -g forum -m 2775 /var/www/forum
install -d -o deploy -g forum -m 2775 /var/www/forum/public
install -d -o deploy -g forum -m 2775 /var/www/forum/public/data
install -d -o deploy -g forum -m 2775 /var/www/forum/server
install -d -o php-dev -g forum -m 2770 /var/www/forum/server/data
```

The group write bit and the setgid bit are both load-bearing: PHP-FPM writes
as `php-dev`, the deploy writes as `deploy`, and anything either of them
creates has to stay writable by the other. The deploy sets the same modes on
what it uploads, so this survives a push.

`server/data` is the only directory PHP has to write, and `public/data` is the
only one it has to write inside the web root — those are the two files the
site reads at runtime, which is how an edit in the admin shows on the page
with no rebuild. Nothing else in the web root is writable.

### 3. Files

```sh
cp -r server/. /var/www/forum/server/          # this directory
cp -r site-2-silkroad/dist/. /var/www/forum/public/   # the built site
```

`/var/www/forum/public` holds `index.html`, `register/index.html`, `assets/`
and `data/` — the output of `npm run build:2`. The registration page is a
directory rather than a `.html` file so that its address reads `/register/`;
`try_files $uri $uri/` below is what serves it.

After this first copy, every later one is the deploy workflow's job — see
**Automatic deploy** below.

### 4. `.env` — the one file

```sh
cd /var/www/forum/server
cp .env.example .env
php bin/password.php                  # asks for the password, writes the hash
chown php-dev:forum .env && chmod 640 .env
```

Then open `.env` and fill in the three that have no sensible default:

```
SITE_DIR=/var/www/forum/public
SITE_URL=https://forum.example.uz
DATA_DIR=/var/www/forum/server/data
```

Everything else in that file is commented, has a default, and can be left
alone to begin with. `FORCE_HTTPS=1` once the certificate answers.

```sh
php bin/doctor.php
```

It checks the PHP version, the extensions, the password, both data
directories, the site directory and the two files the site reads, and says
which one is wrong in the words of the thing that has to change. Run it again
after any change here.

> An installation made before 25 October 2026 has a `src/config.php` instead.
> It still works and still takes precedence; `bin/password.php` moves its
> values into `.env` and renames it the first time it runs.

### 5. nginx

At `http` level, once — a rate limit for the two addresses that are worth
hammering:

```nginx
# /etc/nginx/nginx.conf, inside http { }
server_tokens off;
limit_req_zone $binary_remote_addr zone=forum_admin:10m rate=30r/m;
limit_req_zone $binary_remote_addr zone=forum_api:10m rate=20r/m;
```

Then the site:

```nginx
server {
    listen 80;
    server_name forum.example.uz;
    # Everything else is https. certbot writes this for you; this is what it
    # amounts to.
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name forum.example.uz;

    ssl_certificate     /etc/letsencrypt/live/forum.example.uz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/forum.example.uz/privkey.pem;

    root /var/www/forum/public;
    index index.html;

    # A registration is a couple of kilobytes. Nothing here accepts a file.
    client_max_body_size 64k;

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;

    # Nothing in this tree begins with a dot, so anything that asks for one
    # is asking for something it should not have. .env is outside the root
    # already; this is the second lock.
    location ~ /\. { deny all; }

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

    # The two files the admin rewrites. They must never be served from a
    # cache: the whole point of the editor is that a change is live at once.
    location /data/ {
        add_header Cache-Control "no-cache";
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
        limit_req zone=forum_api burst=10 nodelay;
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

    # The admin. The password is the lock and the throttle is inside PHP;
    # this is the one in front of it. Add `allow`/`deny` here too if it
    # should only open from the office — or set ADMIN_IP_ALLOW in .env,
    # which does the same thing without an nginx reload.
    location /admin/ {
        limit_req zone=forum_admin burst=20 nodelay;
        alias /var/www/forum/server/public/;
        index index.php;

        location ~ ^/admin/(.+\.php)$ {
            include fastcgi_params;
            fastcgi_pass unix:/run/php-fpm84/php-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/forum/server/public/$1;
        }
    }
}
```

### 6. PHP

Two settings matter and one of them is not about safety:

```ini
; /etc/php84/php.ini
display_errors = Off      ; a notice printed into a response lands inside the
                          ; CSV and corrupts it. That is not a theory.
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
```

`validate_timestamps = 1` is what makes a deploy take effect without
reloading anything; turn it off and every deploy needs
`rc-service php-fpm84 reload` after it.

Alpine installs the interpreter as `php84` and often leaves no plain `php` on
PATH. The deploy copes with either, and the commands in this file are shorter
if you make one:

```sh
ln -sf /usr/bin/php84 /usr/local/bin/php
```

```sh
rc-update add php-fpm84 && rc-service php-fpm84 start
rc-update add nginx && rc-service nginx restart
```

### 7. Check it

```sh
curl -s https://forum.example.uz/api/health
# {"ok":true,"store":"SQLite (pdo_sqlite)","detail":"ok"}
```

Then open `https://forum.example.uz/admin/` and log in.

---

## Automatic deploy

Every push to `main` in the repository that carries this directory builds the
site, copies it and the backend to this server, and puts the admin's own
edits back on top. `.github/workflows/deploy-server.yml`.

**On the server, once:** a key for the deploy to log in with.

```sh
# as the deploy user
ssh-keygen -t ed25519 -f ~/.ssh/deploy -N ''
cat ~/.ssh/deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
cat ~/.ssh/deploy          # the private key — this goes into GitHub, once
ssh-keyscan -p 22 forum.example.uz   # the host key — this goes in too
```

**In GitHub,** *Settings → Secrets and variables → Actions*:

| | Name | Value |
|---|---|---|
| Secret | `SSH_KEY` | the whole private key, `-----BEGIN` to `-----END` |
| Secret | `SSH_KNOWN_HOSTS` | the `ssh-keyscan` output |
| Variable | `SSH_HOST` | `forum.example.uz` |
| Variable | `SSH_USER` | `deploy` |
| Variable | `SSH_PORT` | `22` (omit for 22) |
| Variable | `DEPLOY_PATH` | `/var/www/forum` |
| Variable | `SITE_URL` | `https://forum.example.uz` |
| Variable | `REGISTER_ENDPOINT` | empty once the site is on this server |

Until `SSH_HOST` is set the job is skipped rather than failed, so a push to a
repository with no server yet is still a green push.

**What a deploy does not touch:** `server/.env`, `server/data/`. They are
excluded from the copy, which also protects them from `--delete`.

**What it does touch:** everything else under `public/` and `server/`.
`--delete` is on, so a file put into `public/` by hand will be removed by the
next deploy — put it in the repository instead.

**The step that matters.** A deploy replaces `public/data/*.json` with the
build output. Those are exactly the two files the admin publishes into, so
for a moment the site says what the build says and not what the organiser
edited. The last step is therefore:

```sh
php /var/www/forum/server/bin/publish.php
```

which re-reads the new build as the base and re-applies the overlay on top of
it. The organiser's dates, texts and hidden projects survive a push they did
not make. `qa/backend.mjs` has a check for exactly this, and so does the
workflow: the deploy fails if the republish does.

It then runs `bin/doctor.php` on the server, so a deploy onto a
misconfigured installation says so in the deploy log rather than in a
telephone call from the organiser.

---

## While the site is still on GitHub Pages

The published site is served from `*.github.io` and this API is on the
organiser's server, so the browser treats the POST as cross-origin. Two
changes:

1. In `.env`:
   ```
   ALLOWED_ORIGINS=https://fastsoftware-uz.github.io
   ```
   A request from any other origin is refused, so that list is the whole of
   the guest list.

2. The Pages build needs the absolute endpoint: **Settings → Secrets and
   variables → Actions → Variables → `REGISTER_ENDPOINT`**, with
   `https://forum.example.uz/api/register` as its value. The next push picks
   it up.

Until that variable is set the POST goes nowhere, and the page says so and
offers the organiser's address with the answers already filled into the
message — a registration is not lost in the meantime, it just arrives by
email.

When the site moves onto this server, empty both: same-origin needs neither.

**The editor edits the site on this server and nowhere else.** While the
published site is still on Pages, what the organiser changes in the admin
will not show there — the editor rewrites the copy nginx serves.

---

## The API

### `POST /api/register`

```json
{ "role": "vip|investor|visitor|speaker", "full_name": "", "organisation": "",
  "position": "", "country": "", "email": "", "phone": "", "direction": "",
  "note": "", "lang": "uz" }
```

`role`, `full_name`, `organisation`, `position`, `country`, `email` and
`phone` are required. `direction` must be one of the six the site shows or it
is dropped. A form POST is accepted as well as JSON.

| Status | Body | Means |
|---|---|---|
| 200 | `{"ok":true,"id":12}` | Stored. `id` is the reference the visitor is shown. |
| 413 | `{"ok":false,"error":"body"}` | Over 32 KB. Not a registration. |
| 422 | `{"ok":false,"fields":["email"]}` | Named fields are wrong. No prose: the page knows the reader's language and this does not. |
| 429 | `{"ok":false,"error":"rate"}` | More than `RATE_LIMIT_PER_HOUR` from one address. |
| 403 | `{"ok":false,"error":"origin"}` | Cross-origin, and the origin is not in the list. |
| 500 | `{"ok":false,"error":"store"}` | Could not write. The reason is in the PHP error log, not in the response. |

### `GET /api/health`

`{"ok":true,"store":"…","detail":"ok"}` — the quickest way to tell from a
browser that nginx, PHP and the data directory are all talking to each other.

---

## What is guarded, and what is not

**The password.** `password_hash`/`password_verify`, no default, and the
application refuses every login until an installation sets one. A wrong
answer costs 0.4 s however wrong it is, and five of them lock that address
out for fifteen minutes — doubling each time it is earned again, up to a day.
The counter is one small file under a lock, so several PHP-FPM workers cannot
be used to outrun it, and it is only read on a login. The organiser changes
the password from the admin itself: the current one is asked for, the new one
goes into `data/admin.json`, and every other session is ended by the change.
`bin/password.php` is the way back in when it has been forgotten.

**The session.** A fresh id on a successful login, so a session fixed before
it is not the one that ends up logged in; `session.use_strict_mode`, so an id
the server never issued is never adopted. The cookie is `HttpOnly`,
`SameSite=Strict`, and `Secure` whenever the request arrived over HTTPS. It
expires after 30 idle minutes and after 12 hours whatever it was doing.

**The pages.** Every value that reaches one goes through `htmlspecialchars`,
and SQLite is only ever given bound parameters. Every state-changing POST
carries a CSRF token. The admin, the CSV and the e-ticket are sent
`no-store`, so the guest list is not left in a browser's cache on a shared
machine; `noindex`, so it is not in a search engine; `X-Frame-Options: DENY`,
so it cannot be framed; and a content policy of `default-src 'none'` with
nothing inline allowed — which is why the admin's two behaviours live in
`admin.js` rather than in attributes.

**The form.** A honeypot field no human sees: a bot that fills it is answered
`200` and stored nowhere — told it had failed, it would come back having
learned something. One address may send `RATE_LIMIT_PER_HOUR` registrations
an hour. A body over 32 KB is refused before it is parsed.

**Optionally, the door.** `ADMIN_IP_ALLOW` in `.env` — exact addresses and
IPv4 ranges. Empty by default, because organisers travel and an admin they
cannot open from a hotel is an admin somebody will ask to have the password
e-mailed from.

**Not guarded, deliberately.** There is no email confirmation: a registration
is a lead for the organising committee to call, not an account. There is no
backup; `data/` is one directory and belongs in whatever already backs that
server up — including `ticket-secret.key`, without which every badge already
issued stops opening.

**Please also:**

- Serve this over HTTPS and set `FORCE_HTTPS=1`. The admin password crosses
  the wire on every login.
- Set `display_errors = Off`. A PHP notice printed into a response lands
  inside the CSV and corrupts it — that is not a theory, it happened while
  this was being written, and the fix is in the code as well.
- The CSV holds every guest's telephone number and e-mail address. It is for
  the organising committee and no one else.
