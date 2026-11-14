<?php
/**
 * The way back in after a lock-out.
 *
 *     php server/bin/unlock.php
 *
 * One wrong password locks the address it came from — that is the setting
 * this installation runs with, and it is the right one for a door with a
 * guest list behind it. It also means the organiser who mistypes their own
 * password on the morning of the forum is locked out for a quarter of an
 * hour, which on that particular morning is not a quarter of an hour anybody
 * has.
 *
 * So this clears the locks. It refuses to run anywhere but a command line:
 * somebody who can open a shell on this server does not need to guess the
 * password anyway, and a page that did this would be a page that undoes the
 * lock it is standing behind.
 *
 * It also prints who is currently locked out, because the answer "nobody"
 * means the problem is something else and is worth seeing before the file is
 * emptied.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/app.php';
require_once __DIR__ . '/../src/guard.php';

$config = app_config();
$file = guard_login_file($config);

if (!is_file($file)) {
    echo "Hech kim bloklanmagan — urinishlar fayli hali yaratilmagan.\n";
    exit(0);
}

$all = json_decode((string) @file_get_contents($file), true);
$all = is_array($all) ? $all : [];
$now = time();
$locked = 0;

foreach ($all as $address => $row) {
    if (!is_array($row)) {
        continue;
    }
    $until = (int) ($row['until'] ?? 0);
    if ($until > $now) {
        $locked++;
        printf(
            "  %-40s yana %d daqiqa (%d marta bloklangan)\n",
            (string) $address,
            (int) ceil(($until - $now) / 60),
            (int) ($row['locks'] ?? 1),
        );
    }
}

if ($locked === 0) {
    echo "Hozir hech bir manzil bloklangan emas.\n";
} else {
    echo "\n";
}

$cleared = guard_unlock_all($config);
echo $cleared === 0
    ? "Tozalashga narsa yoʻq edi.\n"
    : "Tozalandi: {$cleared} ta manzilning hisobi oʻchirildi. Endi kirib koʻring.\n";

if ((int) ($config['login_one_strike'] ?? 1) === 1) {
    echo "\nEslatma: hozir bitta notoʻgʻri parol ham blokga olib keladi.\n";
    echo "Yumshatish uchun server/.env ga: LOGIN_ONE_STRIKE=0\n";
}
exit(0);
