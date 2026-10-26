<?php
/**
 * Why it is not working.
 *
 *     php server/bin/doctor.php
 *
 * Every way this backend can fail to start is a thing somebody forgot to do
 * to a directory, a package or a file — and every one of them shows up as the
 * same blank page or the same 500. So this asks the questions in order and
 * says which answer is wrong, in the words of the thing that has to change.
 *
 * It reads. It writes one temporary file into the data directory to prove it
 * can, and removes it. Safe to run on the live server, and the first thing to
 * run there.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/app.php';
require_once __DIR__ . '/../src/site.php';
require_once __DIR__ . '/../src/guard.php';
require_once __DIR__ . '/../src/ticket.php';

$problems = 0;
$warnings = 0;

function say(string $state, string $what, string $detail = ''): void
{
    global $problems, $warnings;
    if ($state === 'bad') {
        $problems++;
    } elseif ($state === 'warn') {
        $warnings++;
    }
    $mark = ['ok' => '  ok  ', 'bad' => ' XATO ', 'warn' => ' eslat', 'info' => '      '][$state];
    echo '[' . $mark . '] ' . $what . ($detail === '' ? '' : ' — ' . $detail) . PHP_EOL;
}

function head(string $title): void
{
    echo PHP_EOL . $title . PHP_EOL . str_repeat('-', strlen($title)) . PHP_EOL;
}

/* ------------------------------------------------------------------- php */

head('PHP');

say(
    version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'bad',
    'PHP ' . PHP_VERSION,
    version_compare(PHP_VERSION, '8.1', '>=') ? '' : '8.1 yoki yangisi kerak',
);

foreach (['json' => true, 'session' => true, 'mbstring' => false, 'pdo_sqlite' => false, 'Zend OPcache' => false] as $ext => $required) {
    $have = extension_loaded($ext);
    if ($have) {
        say('ok', $ext);
    } elseif ($required) {
        say('bad', $ext . ' yo\'q', 'apk add php84-' . $ext);
    } else {
        $hint = [
            'mbstring' => 'apk add php84-mbstring — bo\'lmasa ham ishlaydi, lekin uzun ismlarda aniqroq bo\'ladi',
            'pdo_sqlite' => 'apk add php84-pdo php84-pdo_sqlite — bo\'lmasa arizalar matnli faylga yoziladi',
            'Zend OPcache' => 'apk add php84-opcache — tezlik uchun, jonli serverda bo\'lishi kerak',
        ][$ext];
        say('warn', $ext . ' yo\'q', $hint);
    }
}

$displayErrors = (string) ini_get('display_errors');
say(
    in_array(strtolower($displayErrors), ['', '0', 'off', 'false'], true) ? 'ok' : 'warn',
    'display_errors = ' . ($displayErrors === '' ? 'Off' : $displayErrors),
    'jonli serverda Off bo\'lishi kerak: PHP ogohlantirishi CSV faylning ichiga tushib uni buzadi',
);

/* ---------------------------------------------------------------- config */

head('Sozlamalar');

$envFile = env_file();
$legacyFile = __DIR__ . '/../src/config.php';
if (is_file($envFile)) {
    say('ok', '.env topildi', $envFile);
    $perm = @fileperms($envFile);
    if ($perm !== false && (($perm & 0o044) !== 0) && DIRECTORY_SEPARATOR === '/') {
        say('warn', '.env hammaga o\'qiladi', 'chmod 600 ' . $envFile);
    }
} elseif (is_file($legacyFile)) {
    // Configured the old way: not an error, but not where the documentation
    // now points either.
    say('warn', '.env yo\'q', 'sozlamalar eski src/config.php dan o\'qilmoqda');
} else {
    say('bad', '.env yo\'q', 'cp server/.env.example server/.env — va to\'ldiring');
}

if (is_file($legacyFile) && is_file($envFile)) {
    say(
        'warn',
        'src/config.php ham bor',
        'eski usul. U .env dan ustun turadi, ya\'ni .env dagi o\'zgarish ishlamay qolishi mumkin. ' .
        'Qiymatlarni .env ga ko\'chirib, bu faylni o\'chiring',
    );
}

$config = app_config();

$hash = guard_password_hash($config);
$state = guard_admin_state($config);
if ($hash === '') {
    say('bad', 'Adminka paroli o\'rnatilmagan', 'php server/bin/password.php');
} elseif (!str_starts_with($hash, '$2y$') && !str_starts_with($hash, '$argon2')) {
    say('bad', 'ADMIN_PASSWORD_HASH hash emas', 'parolning o\'zi yozilganga o\'xshaydi — php server/bin/password.php');
} else {
    if ($state['hash'] !== '') {
        $from = 'adminkadan o\'zgartirilgan, ' . substr($state['changed_at'], 0, 10);
    } else {
        $inEnv = (string) (env_read($envFile)['ADMIN_PASSWORD_HASH'] ?? '');
        $from = $inEnv !== '' && $inEnv === $hash ? '.env dan' : 'src/config.php dan';
    }
    say('ok', 'Adminka paroli o\'rnatilgan', $from);
}

say($config['force_https'] ? 'ok' : 'warn', 'FORCE_HTTPS=' . ($config['force_https'] ? '1' : '0'),
    $config['force_https'] ? '' : 'adminka paroli har kirishda tarmoqdan o\'tadi — sertifikat ishlagach 1 qiling');

if (($config['admin_ip_allow'] ?? []) === []) {
    say('info', 'ADMIN_IP_ALLOW bo\'sh', 'adminka hamma joydan ochiladi; parol — yagona qulf');
} else {
    say('ok', 'ADMIN_IP_ALLOW', implode(', ', $config['admin_ip_allow']));
}

$url = (string) $config['site_url'];
if ($url === '') {
    say('warn', 'SITE_URL bo\'sh', 'e-bilet QR kodi so\'rov kelgan hostni ishlatadi — proksi orqasida bu xato bo\'lishi mumkin');
} elseif (!str_starts_with($url, 'https://')) {
    say('warn', 'SITE_URL https emas', $url);
} else {
    say('ok', 'SITE_URL', $url);
}

/* ------------------------------------------------------------------ data */

head('Ma\'lumotlar papkasi');

$dataDir = rtrim((string) $config['data_dir'], '/\\');
if (!is_dir($dataDir)) {
    say('bad', 'Papka yo\'q', $dataDir . ' — install -d -o php-dev -g php-dev -m 750 ' . $dataDir);
} else {
    $probe = $dataDir . '/.doctor-' . getmypid();
    if (@file_put_contents($probe, "x\n") === false) {
        say('bad', 'Yozib bo\'lmadi', $dataDir . ' — chown -R php-dev:php-dev ' . $dataDir);
    } else {
        @unlink($probe);
        say('ok', 'Yozish mumkin', $dataDir);
    }
}

say('info', 'Arizalar qayerda', storage_describe());
try {
    $rows = storage_all($config);
    say('ok', 'Arizalar o\'qildi', count($rows) . ' ta');
} catch (Throwable $e) {
    say('bad', 'Arizalarni o\'qib bo\'lmadi', $e->getMessage());
}

$secret = $dataDir . '/ticket-secret.key';
if (is_file($secret)) {
    say('ok', 'Bilet kaliti bor', 'ZAXIRA NUSXASI OLINSIN: o\'chsa, berilgan biletlar ochilmaydi');
} else {
    say('info', 'Bilet kaliti hali yo\'q', 'birinchi ariza kelganda o\'zi yaratiladi');
}

/* ------------------------------------------------------------------ site */

head('Sayt');

$siteDir = site_dir($config);
if (!is_dir($siteDir)) {
    say('bad', 'Sayt papkasi yo\'q', $siteDir . ' — .env dagi SITE_DIR ni tekshiring');
} else {
    say('ok', 'Sayt papkasi', $siteDir);
    say(is_file($siteDir . '/index.html') ? 'ok' : 'bad', 'index.html',
        is_file($siteDir . '/index.html') ? '' : 'bu papkada build natijasi yo\'q');

    foreach (SITE_FILES as $key => $meta) {
        $file = site_published_file($config, $key);
        if (!is_file($file)) {
            say('bad', $meta['file'], 'yo\'q — adminkadan saytni tahrirlash ishlamaydi');
            continue;
        }
        $overrides = site_overrides($config, $key);
        say('ok', $meta['file'], count($overrides['values']) . ' ta o\'zgartirish saqlangan');
    }

    $siteData = $siteDir . '/data';
    if (!is_dir($siteData)) {
        say('bad', 'data/ papkasi yo\'q', $siteData);
    } else {
        $probe = $siteData . '/.doctor-' . getmypid();
        if (@file_put_contents($probe, "x\n") === false) {
            say('bad', 'data/ ga yozib bo\'lmadi', 'chown -R php-dev:php-dev ' . $siteData . ' — adminka saytni tahrirlay olmaydi');
        } else {
            @unlink($probe);
            say('ok', 'data/ ga yozish mumkin', $siteData);
        }
    }
}

/* --------------------------------------------------------------- summary */

echo PHP_EOL;
if ($problems === 0 && $warnings === 0) {
    echo 'Hammasi joyida.' . PHP_EOL;
} elseif ($problems === 0) {
    echo 'Ishlaydi. ' . $warnings . ' ta eslatma bor (yuqorida).' . PHP_EOL;
} else {
    echo $problems . ' ta xato topildi — adminka shu holatda ishlamaydi.' . PHP_EOL;
}
exit($problems === 0 ? 0 : 1);
