<?php
/**
 * What stands between the guest list and the open internet.
 *
 * The admin is one password on a public address. Everything in this file is
 * there because that sentence is true:
 *
 *   - the password is slow to guess and the guessing stops after a few tries;
 *   - the session expires on its own, and dies everywhere when the password
 *     changes;
 *   - no page that shows a name, a telephone number or an e-mail address is
 *     ever stored by a browser, a proxy or a search engine;
 *   - the page cannot be framed, cannot load anything from anywhere else, and
 *     cannot post anywhere else.
 *
 * None of it costs a database query or a round trip. The throttle is one
 * small JSON file read under a lock, and only on the login request.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/** Did this request arrive over TLS, directly or through one proxy? */
function guard_is_https(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';
    if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
        return true;
    }
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * Send the headers that make an admin page an admin page.
 *
 * `no-store` is the one that matters most and the one nobody remembers: the
 * table holds every guest's telephone number, and the default is for the
 * browser to keep it on disk and hand it back on the back button after the
 * organiser has logged out and walked away from a shared computer.
 *
 * The content policy is `'none'` by default and opens only what these pages
 * actually use — their own stylesheet and their own script. There is no
 * inline script in the admin; the two handlers that used to be attributes
 * live in admin.js precisely so that this header can stay strict.
 */
function guard_headers(string $kind = 'admin'): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');
    header_remove('X-Powered-By');

    if ($kind === 'admin') {
        header(
            "Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; "
            . "img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        );
    } elseif ($kind === 'ticket') {
        // The badge draws its own CSS into the page: it is printed from a
        // saved file as often as from the network, and a badge whose styles
        // are a separate request is a blank sheet of paper.
        header(
            "Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data:; form-action 'none'; base-uri 'none'; frame-ancestors 'none'",
        );
    }

    /**
     * HSTS, but only on a request that already arrived over HTTPS.
     *
     * Sent on a plain-HTTP request it would do nothing; sent before the
     * certificate works it would lock the organiser out of their own server
     * for the lifetime of the header, which no amount of nginx can undo.
     */
    if (guard_is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/**
 * Insist on HTTPS, when the configuration says to.
 *
 * Off by default: a server whose certificate is not in place yet would
 * redirect the installer into a loop on the first request, and the first
 * request is where they would have to debug it from. Turn it on in .env once
 * the certificate answers.
 */
function guard_require_https(array $config): void
{
    if (empty($config['force_https']) || guard_is_https()) {
        return;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host) !== 1) {
        http_response_code(400);
        exit;
    }
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

/* ------------------------------------------------------------- the password */

function guard_admin_file(array $config): string
{
    return rtrim((string) $config['data_dir'], '/\\') . '/admin.json';
}

/**
 * What was changed from the admin itself, if anything.
 *
 * @return array{hash: string, version: int, changed_at: string}
 */
function guard_admin_state(array $config): array
{
    $file = guard_admin_file($config);
    $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    if (!is_array($data)) {
        return ['hash' => '', 'version' => 0, 'changed_at' => ''];
    }
    return [
        'hash' => (string) ($data['hash'] ?? ''),
        'version' => (int) ($data['version'] ?? 0),
        'changed_at' => (string) ($data['changed_at'] ?? ''),
    ];
}

/**
 * The hash to check a login against.
 *
 * data/admin.json wins over .env, so that changing the password from the
 * admin takes effect without anybody editing a file on the server — and so
 * that a deploy, which replaces .env's neighbours and may replace .env
 * itself, cannot quietly restore the password the installation started with.
 */
function guard_password_hash(array $config): string
{
    $own = guard_admin_state($config)['hash'];
    return $own !== '' ? $own : (string) ($config['admin_password_hash'] ?? '');
}

/** How many times the password has been changed from the admin. */
function guard_password_version(array $config): int
{
    return guard_admin_state($config)['version'];
}

/**
 * Set a new password.
 *
 * Writes the data directory rather than .env: PHP-FPM has to be able to
 * write data/ anyway — the registrations are there — and a web process that
 * can rewrite its own configuration file is a web process that can rewrite
 * `site_dir` and `data_dir` too.
 *
 * The version goes up, which is how every other browser that is logged in
 * finds out. See guard_admin_session().
 */
function guard_password_set(array $config, string $plain): void
{
    $file = guard_admin_file($config);
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException("Maʼlumotlar papkasini yaratib boʻlmadi: {$dir}");
    }
    $state = guard_admin_state($config);
    $payload = [
        'hash' => password_hash($plain, PASSWORD_DEFAULT),
        'version' => $state['version'] + 1,
        'changed_at' => gmdate('c'),
    ];
    $tmp = $file . '.' . getmypid() . '.tmp';
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException("Yozib boʻlmadi: {$file}");
    }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException("Oʻrniga qoʻyib boʻlmadi: {$file}");
    }
}

/** Is this good enough to be the only lock on the guest list? */
function guard_password_complaint(string $plain): string
{
    $length = function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
    if ($length < 10) {
        return 'short';
    }
    if (preg_match('/^\d+$/', $plain) === 1) {
        return 'digits';
    }
    return '';
}

/* -------------------------------------------------------------- the session */

/**
 * Start the admin session, and end it when it should have ended.
 *
 * Three ways it ends by itself: an idle browser (SESSION_IDLE_MINUTES), a
 * session that has simply been open too long whatever it was doing
 * (SESSION_MAX_HOURS), and a password that has been changed somewhere else
 * since this one logged in.
 */
function guard_admin_session(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    /**
     * Refuse a session id the server did not issue.
     *
     * Without strict mode PHP adopts whatever id the cookie carries, which
     * is the whole of session fixation: a link with an id in it, sent to the
     * organiser, and the sender knows the id that ends up logged in.
     */
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name('forum_admin');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => guard_is_https(),
        'path' => '/',
    ]);
    session_start();

    /*
     * session_start() rewrites Cache-Control from session.cache_limiter and
     * drops what guard_headers() sent. Its default ('nocache') is nearly the
     * same thing, but "nearly" is not a word to leave in the only header that
     * keeps a guest list out of a browser's disk cache.
     */
    header('Cache-Control: no-store, no-cache, must-revalidate, private');

    if (empty($_SESSION['admin'])) {
        return;
    }

    $now = time();
    $idle = max(1, (int) $config['session_idle_minutes']) * 60;
    $life = max(1, (int) $config['session_max_hours']) * 3600;
    $born = (int) ($_SESSION['born'] ?? $now);
    $seen = (int) ($_SESSION['seen'] ?? $now);
    $stale = ($now - $seen) > $idle || ($now - $born) > $life;
    $revoked = (int) ($_SESSION['pwv'] ?? 0) !== guard_password_version($config);

    if ($stale || $revoked) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['ended'] = $revoked ? 'password' : 'timeout';
        return;
    }
    $_SESSION['seen'] = $now;
}

/** Mark the session as logged in. Called on a verified password, only. */
function guard_admin_login(array $config): void
{
    // A fresh id, so a session fixed before the login is not the one that
    // ends up logged in.
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['born'] = time();
    $_SESSION['seen'] = time();
    $_SESSION['pwv'] = guard_password_version($config);
}

/* ------------------------------------------------------------- the throttle */

function guard_login_file(array $config): string
{
    return rtrim((string) $config['data_dir'], '/\\') . '/login-attempts.json';
}

/**
 * Read, change and write the attempt file under one lock.
 *
 * Several PHP-FPM workers can be answering login attempts at the same time —
 * that is what makes an unlocked counter useless against exactly the thing it
 * is counting.
 *
 * @param callable(array<string,array{n:int,until:int}>):array<string,array{n:int,until:int}> $change
 * @return array<string,array{n:int,until:int}>
 */
function guard_attempts(array $config, callable $change): array
{
    $file = guard_login_file($config);
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        // A throttle that cannot write must not be a login that cannot happen.
        return $change([]);
    }
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return $change([]);
    }
    @chmod($file, 0600);
    try {
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $all = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        $all = is_array($all) ? $all : [];

        $now = time();
        /*
         * Forget an address that has been quiet for a day, so the file stays
         * a handful of lines however long the forum runs.
         *
         * Measured from the last attempt, not from the lock: a row that is
         * still counting has no lock yet, and pruning on `until` threw those
         * rows away on the very next request — which is a throttle that
         * counts to one for ever. That is not a theory; it is what the first
         * version of this did, and the check that caught it is in
         * qa/backend.mjs.
         */
        foreach ($all as $addr => $row) {
            if (!is_array($row)) {
                unset($all[$addr]);
                continue;
            }
            $last = max((int) ($row['until'] ?? 0), (int) ($row['seen'] ?? 0));
            if ($last + 86400 < $now) {
                unset($all[$addr]);
            }
        }

        $all = $change($all);

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) json_encode($all, JSON_UNESCAPED_SLASHES));
        fflush($fh);
        return $all;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/** Seconds this address still has to wait, or 0 if it may try now. */
function guard_login_wait(array $config, string $ip): int
{
    if ($ip === '') {
        return 0;
    }
    $all = guard_attempts($config, static fn(array $a): array => $a);
    $until = (int) ($all[$ip]['until'] ?? 0);
    return max(0, $until - time());
}

/**
 * Count a wrong password, and lock the address when there have been enough.
 *
 * The lock doubles each time it is earned again, up to a day: somebody who
 * is working through a word list is stopped for good, and the organiser who
 * mistyped twice waits a quarter of an hour at worst.
 */
function guard_login_failed(array $config, string $ip): void
{
    if ($ip === '') {
        return;
    }
    $max = max(1, (int) $config['login_max_attempts']);
    $minutes = max(1, (int) $config['login_lock_minutes']);
    guard_attempts($config, static function (array $all) use ($ip, $max, $minutes): array {
        $row = is_array($all[$ip] ?? null) ? $all[$ip] : ['n' => 0, 'until' => 0, 'locks' => 0];
        // An hour of quiet forgives the count. Four wrong guesses spread over
        // a year are somebody mistyping, not somebody working through a list.
        if ((int) ($row['seen'] ?? 0) + 3600 < time()) {
            $row['n'] = 0;
        }
        $row['seen'] = time();
        $row['n'] = (int) ($row['n'] ?? 0) + 1;
        if ($row['n'] >= $max) {
            $locks = min(6, (int) ($row['locks'] ?? 0) + 1);
            $row['locks'] = $locks;
            $row['until'] = time() + $minutes * 60 * (2 ** ($locks - 1));
            $row['n'] = 0;
        }
        $all[$ip] = $row;
        return $all;
    });
}

/** A right password forgets every wrong one from that address. */
function guard_login_ok(array $config, string $ip): void
{
    if ($ip === '') {
        return;
    }
    guard_attempts($config, static function (array $all) use ($ip): array {
        unset($all[$ip]);
        return $all;
    });
}

/* -------------------------------------------------------------- the ip lock */

/**
 * The optional second lock: ADMIN_IP_ALLOW.
 *
 * Empty means "from anywhere", which is the right default — a forum's
 * organisers travel, and an admin they cannot open from a hotel is an admin
 * somebody will ask to have the password e-mailed from. Set it to the
 * office's address and the password becomes the second lock rather than the
 * only one.
 *
 * Exact addresses and IPv4 CIDR ranges. An entry that is neither matches
 * nothing rather than everything.
 */
function guard_ip_allowed(array $config, string $ip): bool
{
    $list = $config['admin_ip_allow'] ?? [];
    if (!is_array($list) || $list === []) {
        return true;
    }
    foreach ($list as $entry) {
        $entry = trim((string) $entry);
        if ($entry === '') {
            continue;
        }
        if ($entry === $ip) {
            return true;
        }
        if (!str_contains($entry, '/')) {
            continue;
        }
        [$net, $bits] = explode('/', $entry, 2);
        $a = ip2long($net);
        $b = ip2long($ip);
        $bits = (int) $bits;
        if ($a === false || $b === false || $bits < 0 || $bits > 32) {
            continue;
        }
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits)) & 0xFFFFFFFF;
        if ((($a & $mask) & 0xFFFFFFFF) === (($b & $mask) & 0xFFFFFFFF)) {
            return true;
        }
    }
    return false;
}
