<?php
/**
 * The picture of the login check.
 *
 * The only page behind the admin's door that answers without a session being
 * logged in — it has to, it is the thing in front of the login. It carries no
 * information: the answer lives in the session and never leaves the server,
 * and this draws what is already there rather than making anything new. Asked
 * for twice, it returns the same five characters, which is what lets a reader
 * reload a smudged image without the answer moving underneath them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/app.php';
require_once __DIR__ . '/../src/guard.php';
require_once __DIR__ . '/../src/captcha.php';

$config = app_config();
guard_require_https($config);
guard_admin_session($config);

header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header_remove('X-Powered-By');

if (!captcha_can_draw()) {
    /* No GD: the login page asks the question in words instead, and never
       reaches for this. Answered plainly so a stale page says something
       sensible rather than showing a broken image. */
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Rasm chizib boʻlmaydi.\n";
    exit;
}

/**
 * Only ever draws a challenge that already exists.
 *
 * The login page makes one when it renders; this draws it. Asked for on its
 * own, with no challenge in the session, it answers 404 and makes nothing —
 * which also means it writes no session file, because PHP only stores a
 * session that has something in it. Otherwise a loop on this address would
 * be a loop filling the session directory with challenges nobody asked a
 * question with.
 */
if (!isset($_SESSION['captcha'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Tekshiruv savoli yoʻq — kirish sahifasini oching.\n";
    exit;
}

try {
    $png = captcha_png($config);
} catch (Throwable $e) {
    error_log('[forum-admin] captcha: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
echo $png;
