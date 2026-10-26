<?php
/**
 * Set the admin password.
 *
 *     php server/bin/password.php                 # asks, and does not echo
 *     php server/bin/password.php "the password"  # for a script
 *
 * Writes the hash into server/.env, creating it from .env.example if it is
 * not there yet. The password itself is never written anywhere — not into the
 * file, not into the shell history when it is typed rather than passed.
 *
 * If the password had been changed from the admin before now, that change
 * lives in data/admin.json and takes precedence over .env (see
 * guard_password_hash). So this removes that file: .env becomes the answer
 * again, and every browser that was logged in is logged out.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/app.php';
require_once __DIR__ . '/../src/guard.php';

$plain = trim(implode(' ', array_slice($argv, 1)));
if ($plain === '') {
    // npm run server:password passes it this way: argv is readable in `ps`
    // on a shared machine and the environment of one process is not.
    $fromEnv = getenv('FORUM_NEW_PASSWORD');
    $plain = is_string($fromEnv) ? trim($fromEnv) : '';
}

if ($plain === '') {
    /**
     * Ask, with the echo off where the shell allows it.
     *
     * `stty` is POSIX and is what Alpine has; on Windows there is no
     * equivalent that works through a pipe, so the characters show and the
     * script says so rather than pretending they do not.
     */
    $hidden = DIRECTORY_SEPARATOR === '/' && @shell_exec('command -v stty') !== null;
    if ($hidden) {
        shell_exec('stty -echo');
    } else {
        echo 'Diqqat: parol ekranda ko\'rinadi.' . PHP_EOL;
    }
    echo 'Yangi parol: ';
    $plain = rtrim((string) fgets(STDIN), "\r\n");
    if ($hidden) {
        echo PHP_EOL . 'Yana bir marta: ';
    } else {
        echo 'Yana bir marta: ';
    }
    $again = rtrim((string) fgets(STDIN), "\r\n");
    if ($hidden) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    if ($plain !== $again) {
        fwrite(STDERR, 'Ikki marta kiritilgani bir xil emas.' . PHP_EOL);
        exit(1);
    }
}

$complaint = guard_password_complaint($plain);
if ($complaint === 'short') {
    fwrite(STDERR, 'Kamida 10 belgi bo\'lsin. Adminka ochiq internetda turadi.' . PHP_EOL);
    exit(1);
}
if ($complaint === 'digits') {
    fwrite(STDERR, 'Faqat raqamdan iborat parol bo\'lmaydi.' . PHP_EOL);
    exit(1);
}

$hash = password_hash($plain, PASSWORD_DEFAULT);

$file = env_file();
$example = __DIR__ . '/../.env.example';
if (!is_file($file)) {
    if (!is_file($example)) {
        fwrite(STDERR, "Topilmadi: {$example}" . PHP_EOL);
        exit(1);
    }
    copy($example, $file);
    @chmod($file, 0600);
    echo 'Yaratildi: ' . $file . ' (.env.example dan) — qolgan qiymatlarni to\'ldirish kerak.' . PHP_EOL;
}

$text = (string) file_get_contents($file);
$line = 'ADMIN_PASSWORD_HASH=' . $hash;
/*
 * Anchored to the start of a line, and replaced through a callback.
 *
 * Anchored because .env.example names this setting in its own comments too,
 * and an unanchored replace rewrites a comment and leaves the setting empty —
 * a file that reads right and refuses every login.
 *
 * Through a callback because a bcrypt hash is `$2y$12$…`, and a replacement
 * string is scanned for `$1`, `$12` and friends after it is built, so the
 * hash would quietly lose characters to the pattern's own capture groups.
 */
$replaced = preg_replace_callback(
    '/^[ \t]*(?:export[ \t]+)?ADMIN_PASSWORD_HASH[ \t]*=.*$/m',
    static fn(): string => $line,
    $text,
    1,
    $count,
);
if ($replaced === null) {
    fwrite(STDERR, 'Faylni o\'qib bo\'lmadi: ' . $file . PHP_EOL);
    exit(1);
}
if ($count === 0) {
    $replaced = rtrim($text) . PHP_EOL . $line . PHP_EOL;
}

$tmp = $file . '.' . getmypid() . '.tmp';
if (@file_put_contents($tmp, $replaced) === false || !@rename($tmp, $file)) {
    @unlink($tmp);
    fwrite(STDERR, 'Yozib bo\'lmadi: ' . $file . PHP_EOL);
    exit(1);
}
@chmod($file, 0600);

/**
 * The old PHP config, carried over and retired.
 *
 * src/config.php takes precedence over .env on purpose — an installation set
 * up before .env existed must not change behaviour under its owner's feet.
 * But leaving it there after somebody has started editing .env is how a
 * setting gets changed in the file that is no longer read. So: its values are
 * written into .env where .env has none, and the file is renamed.
 */
$legacy = __DIR__ . '/../src/config.php';
/*
 * Only when src/config.php is the file that is actually being read.
 *
 * $FORUM_CONFIG and $FORUM_ENV mean somebody is running against an
 * installation that is not this directory — a QA sandbox, or a second
 * instance — and renaming the real config.php from inside that run is a
 * script reaching outside the thing it was pointed at. It did, once, in
 * testing: that is why this condition is here.
 */
$pointedElsewhere = ($e = getenv('FORUM_CONFIG')) !== false && is_string($e) && $e !== '' && is_file($e);
$pointedElsewhere = $pointedElsewhere || (($e = getenv('FORUM_ENV')) !== false && is_string($e) && $e !== '');
if (!$pointedElsewhere && is_file($legacy)) {
    $old = require $legacy;
    $present = env_read($file);
    $added = [];
    if (is_array($old)) {
        foreach (ENV_MAP as $key => [$name, $type]) {
            if ($key === 'ADMIN_PASSWORD_HASH' || !isset($old[$name])) {
                continue;
            }
            $value = $old[$name];
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } else {
                $value = (string) $value;
            }
            if (trim($value) === '' || trim((string) ($present[$key] ?? '')) !== '') {
                continue;
            }
            $added[$key] = $value;
        }
    }
    $text = (string) file_get_contents($file);
    foreach ($added as $key => $value) {
        $line = $key . '=' . $value;
        $hit = 0;
        $text = preg_replace_callback(
            '/^[ \t]*(?:export[ \t]+)?' . preg_quote($key, '/') . '[ \t]*=.*$/m',
            static fn(): string => $line,
            $text,
            1,
            $hit,
        ) ?? $text;
        if ($hit === 0) {
            $text = rtrim($text) . PHP_EOL . $line . PHP_EOL;
        }
    }
    file_put_contents($file, $text);
    @chmod($file, 0600);

    $retired = $legacy . '.bak';
    if (@rename($legacy, $retired)) {
        echo 'src/config.php -> src/config.php.bak (endi .env o\'qiladi).' . PHP_EOL;
        if ($added !== []) {
            echo '  ko\'chirildi: ' . implode(', ', array_keys($added)) . PHP_EOL;
        }
    } else {
        fwrite(STDERR, 'Eslatma: src/config.php o\'z joyida qoldi va .env dan ustun turadi.' . PHP_EOL);
    }
}

$config = app_config();
$own = guard_admin_file($config);
if (is_file($own)) {
    @unlink($own);
    echo 'data/admin.json o\'chirildi — oldin adminkadan qo\'yilgan parol bekor qilindi.' . PHP_EOL;
}

echo 'Parol o\'rnatildi: ' . $file . PHP_EOL;
echo 'Kirgan sessiyalar yopildi. Tekshirish: php server/bin/doctor.php' . PHP_EOL;
