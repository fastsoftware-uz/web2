<?php
/**
 * The one file an installation edits: server/.env.
 *
 * Everything that differs between this machine, the staging server and the
 * live one is a KEY=value line in that file. It is not PHP — a config that
 * can be edited wrongly into a syntax error is a config that takes the whole
 * site down with it, and the person who sets a forum up is not necessarily
 * the person who writes the PHP.
 *
 * Three layers, each overriding the one before:
 *
 *   1. ENV_DEFAULTS below — safe everywhere, and never a working password.
 *   2. server/.env (or the file $FORUM_ENV names).
 *   3. src/config.php, if it still exists, or the file $FORUM_CONFIG names —
 *      the old PHP array. Honoured so that an installation set up before
 *      25 October 2026 keeps working untouched, and used by the QA suites,
 *      which need their own password and their own data directory.
 *
 * The admin password is the one setting that can also come from a fourth
 * place: data/admin.json, written when somebody changes it from the admin
 * itself. See guard_password_hash() in guard.php. .env therefore holds the
 * password the installation *started* with, and stops being the answer the
 * moment it is changed — which is said out loud in .env.example.
 */

declare(strict_types=1);

/**
 * Defaults.
 *
 * `admin_password_hash` is deliberately empty: the admin refuses every login
 * until an installation sets one, rather than falling back to something a
 * reader of this repository would already know.
 */
const ENV_DEFAULTS = [
    'site_name' => 'Tourism Investment Forum 2026',
    'site_url' => '',
    'site_dir' => '',
    'data_dir' => '',
    'storage' => 'auto',
    'admin_password_hash' => '',
    'admin_ip_allow' => [],
    'allowed_origins' => [],
    'rate_limit_per_hour' => 20,
    'notify_email' => '',
    'session_idle_minutes' => 30,
    'session_max_hours' => 12,
    'login_max_attempts' => 5,
    'login_lock_minutes' => 15,
    'force_https' => false,
];

/**
 * Which KEY in .env sets which setting, and what shape it has.
 *
 * A key that is not in this table is ignored rather than stored: .env is also
 * where somebody will reasonably put a note to themselves, and a typo should
 * not become a setting nothing reads.
 */
const ENV_MAP = [
    'SITE_NAME' => ['site_name', 'string'],
    'SITE_URL' => ['site_url', 'string'],
    'SITE_DIR' => ['site_dir', 'string'],
    'DATA_DIR' => ['data_dir', 'string'],
    'STORAGE' => ['storage', 'string'],
    'ADMIN_PASSWORD_HASH' => ['admin_password_hash', 'string'],
    'ADMIN_IP_ALLOW' => ['admin_ip_allow', 'list'],
    'ALLOWED_ORIGINS' => ['allowed_origins', 'list'],
    'RATE_LIMIT_PER_HOUR' => ['rate_limit_per_hour', 'int'],
    'NOTIFY_EMAIL' => ['notify_email', 'string'],
    'SESSION_IDLE_MINUTES' => ['session_idle_minutes', 'int'],
    'SESSION_MAX_HOURS' => ['session_max_hours', 'int'],
    'LOGIN_MAX_ATTEMPTS' => ['login_max_attempts', 'int'],
    'LOGIN_LOCK_MINUTES' => ['login_lock_minutes', 'int'],
    'FORCE_HTTPS' => ['force_https', 'bool'],
];

/** The env file this request reads. `$FORUM_ENV` wins, for the QA suites. */
function env_file(): string
{
    $named = getenv('FORUM_ENV');
    if (is_string($named) && $named !== '') {
        return $named;
    }
    return dirname(__DIR__) . '/.env';
}

/**
 * Parse a KEY=value file.
 *
 * Deliberately not a dotenv implementation: no interpolation, no multi-line
 * values, no `${OTHER}`, and no inline comments — a bcrypt hash is full of
 * `$` and a password may contain a `#`, and a parser clever enough to be
 * surprising here is a parser that locks the organiser out on a Friday.
 * A comment is a line whose first non-blank character is `#`.
 *
 * @return array<string,string>
 */
function env_parse(string $text): array
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        // `export KEY=value` is what somebody who has used a shell will write.
        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }
        $at = strpos($line, '=');
        if ($at === false) {
            continue;
        }
        $key = strtoupper(trim(substr($line, 0, $at)));
        $value = trim(substr($line, $at + 1));
        $quoted = strlen($value) >= 2
            && ($value[0] === '"' || $value[0] === "'")
            && $value[strlen($value) - 1] === $value[0];
        if ($quoted) {
            $value = substr($value, 1, -1);
        }
        $out[$key] = $value;
    }
    return $out;
}

/** @return array<string,string> */
function env_read(string $file): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }
    $text = @file_get_contents($file);
    return $text === false ? [] : env_parse($text);
}

/** One raw value, cast to the shape ENV_MAP gives it. */
function env_cast(string $raw, string $type): mixed
{
    if ($type === 'int') {
        return (int) $raw;
    }
    if ($type === 'bool') {
        return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
    }
    if ($type === 'list') {
        $parts = array_map('trim', explode(',', $raw));
        return array_values(array_filter($parts, static fn(string $p): bool => $p !== ''));
    }
    return $raw;
}

/**
 * The whole configuration, assembled.
 *
 * `data_dir` is resolved here rather than left empty, because everything that
 * writes anything — the registrations, the overlay, the ticket key, the login
 * throttle — asks for it and none of them should carry a fallback of their
 * own.
 */
function env_config(): array
{
    $config = ENV_DEFAULTS;

    foreach (env_read(env_file()) as $key => $raw) {
        if (!isset(ENV_MAP[$key])) {
            continue;
        }
        [$name, $type] = ENV_MAP[$key];
        $value = env_cast($raw, $type);
        // An empty line in .env means "leave the default alone", so that
        // commenting a value out and emptying it do the same thing.
        if ($value === '' || $value === []) {
            continue;
        }
        $config[$name] = $value;
    }

    $legacy = getenv('FORUM_CONFIG');
    $legacyFile = is_string($legacy) && $legacy !== '' && is_file($legacy)
        ? $legacy
        : __DIR__ . '/config.php';
    if (is_file($legacyFile)) {
        $old = require $legacyFile;
        if (is_array($old)) {
            foreach ($old as $name => $value) {
                if ($value === '' || $value === [] || $value === null) {
                    continue;
                }
                $config[$name] = $value;
            }
        }
    }

    if (trim((string) $config['data_dir']) === '') {
        $config['data_dir'] = dirname(__DIR__) . '/data';
    }

    return $config;
}
