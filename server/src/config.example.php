<?php
/**
 * Everything that is specific to one installation.
 *
 * Copy to config.php, edit, and keep it out of the web root — nginx is told
 * to serve only server/public, so this file is not reachable over HTTP even
 * if that is forgotten. Nothing here has a default that would be unsafe if
 * the copy were skipped: the admin password has no default at all, and the
 * application refuses to start without one.
 */

declare(strict_types=1);

return [
    /**
     * Where the registrations are kept. Must be writable by the PHP-FPM user
     * (php-dev on this server) and must NOT be inside public/.
     */
    'data_dir' => dirname(__DIR__) . '/data',

    /**
     * 'auto' | 'sqlite' | 'jsonl'.
     *
     * 'auto' uses SQLite when php-pdo_sqlite is installed and an append-only
     * JSON-lines file when it is not — Alpine ships PHP's extensions as
     * separate apk packages and php84-pdo_sqlite is often not among them, so
     * a backend that assumes it is a backend that 500s on arrival. Set
     * 'jsonl' to insist on the flat file, which you can read with `cat` and
     * count with `wc -l`.
     */
    'storage' => 'auto',

    /**
     * The admin password, as a hash. Generate one with:
     *
     *   php -r 'echo password_hash("the password you chose", PASSWORD_DEFAULT), "\n";'
     *
     * The plain password is never written down anywhere, here or in the
     * database. Leave this empty and the admin refuses every login rather
     * than falling back to something guessable.
     */
    'admin_password_hash' => '',

    /**
     * Where the built site is — the directory holding index.html, assets/
     * and data/.
     *
     * The admin writes the site's own data/*.json so that a change to a date,
     * a contact or a project shows on the page immediately, with no rebuild.
     * It never edits them blind: see src/site.php — the build output is kept
     * as a base and the admin's changes are an overlay on top of it, so a
     * redeploy cannot silently lose them.
     *
     * Leave it empty and the repository's own build output is used, which is
     * what `npm run server` serves. On the server set it to the nginx root:
     *
     *   'site_dir' => '/var/www/forum/public',
     *
     * The PHP-FPM user must be able to write data/ inside it. If it cannot,
     * the registrations still work and only the site editor is unavailable.
     */
    'site_dir' => '',

    /** Shown in the admin header, so you can tell staging from live. */
    'site_name' => 'Tourism Investment Forum 2026',

    /**
     * Which origins may POST a registration from a browser.
     *
     * Empty is the right answer when nginx serves the built site and this
     * API from the same host, which is what the README recommends: no CORS
     * headers are sent and none are needed. Add an origin only while the
     * site is still on GitHub Pages and the API is here, e.g.
     *
     *   'allowed_origins' => ['https://fastsoftware-uz.github.io'],
     *
     * A request from anywhere else is answered 403 rather than quietly
     * accepted, so the list is the whole of the guest list.
     */
    'allowed_origins' => [],

    /**
     * How many registrations one IP may send in an hour. A public form with
     * no limit is a mailbox anybody can fill; this is high enough that a
     * delegation registering together from one office never notices it.
     */
    'rate_limit_per_hour' => 20,

    /**
     * Optional. When set, each new registration is also mailed here through
     * the server's own mail(). Leave empty to switch the mail off entirely —
     * the registration is stored either way, and a mail that cannot be sent
     * never fails the request.
     */
    'notify_email' => '',
];
