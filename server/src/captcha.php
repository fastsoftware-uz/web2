<?php
/**
 * The question in front of the password: is there a person here?
 *
 * The admin is one address on the open internet and the password is what
 * stands behind it. A throttle counts attempts from an address; a check that
 * a person is typing stops the attempt being made at all, which is the only
 * thing that helps when the attempts come from a thousand addresses one each.
 *
 * ## Two of them, and the first needs nothing
 *
 * The built-in check is five characters drawn into an image by GD, or — where
 * GD is not installed — a sum written out in Uzbek words. It asks nothing of
 * the network, which matters: the organiser's server may sit behind a
 * firewall with no outbound route, and a login that depends on reaching
 * somebody else's API is a login that fails on the morning of the forum.
 *
 * Cloudflare Turnstile is used instead the moment the two keys are filled in.
 * It is the better check — it watches the browser rather than the eyes — and
 * it is opt-in for exactly the reason above.
 *
 * ## The order it runs in
 *
 * The question is answered before the password is looked at. A wrong answer
 * is refused without the password being verified at all, and without counting
 * against the password throttle: misreading five drawn characters is a thing
 * people do, and with a one-strike lock in place it must not be the thing
 * that locks the organiser out. A wrong answer has its own cheap limit, in
 * the session, so it cannot be used to keep the server busy either.
 */

declare(strict_types=1);

/** The alphabet, with every pair that can be misread taken out: 0/O, 1/I/l. */
const CAPTCHA_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const CAPTCHA_LENGTH = 5;
/** A challenge is good for this long, and for one answer. */
const CAPTCHA_TTL = 600;
/** Wrong answers in a row before a fresh challenge is forced. */
const CAPTCHA_MAX_TRIES = 6;

/** Is the Cloudflare check configured? */
function captcha_turnstile(array $config): bool
{
    return trim((string) ($config['turnstile_site_key'] ?? '')) !== ''
        && trim((string) ($config['turnstile_secret'] ?? '')) !== '';
}

/** Which check this installation uses: 'turnstile', 'image' or 'words'. */
function captcha_kind(array $config): string
{
    if (captcha_turnstile($config)) {
        return 'turnstile';
    }
    return captcha_can_draw() ? 'image' : 'words';
}

function captcha_can_draw(): bool
{
    return function_exists('imagecreatetruecolor')
        && function_exists('imagepng')
        && function_exists('imagestring');
}

/* --------------------------------------------------------- the built-in */

/**
 * The current challenge, making a new one when there is none, when the old
 * one has expired, or when it has been answered wrongly too many times.
 *
 * The answer is kept in the session and never leaves the server. What the
 * reader gets is the drawing of it.
 *
 * @return array{text:string,words:string,born:int,tries:int}
 */
function captcha_challenge(array $config): array
{
    $now = time();
    $held = $_SESSION['captcha'] ?? null;
    if (
        is_array($held)
        && isset($held['text'], $held['born'])
        && (int) $held['born'] + CAPTCHA_TTL > $now
        && (int) ($held['tries'] ?? 0) < CAPTCHA_MAX_TRIES
    ) {
        return $held;
    }
    return captcha_renew($config);
}

/** @return array{text:string,words:string,born:int,tries:int} */
function captcha_renew(array $config): array
{
    if (captcha_can_draw()) {
        $text = '';
        $max = strlen(CAPTCHA_ALPHABET) - 1;
        for ($i = 0; $i < CAPTCHA_LENGTH; $i++) {
            $text .= CAPTCHA_ALPHABET[random_int(0, $max)];
        }
        $words = '';
    } else {
        // No GD: a sum, written out, so that reading it is still a small act
        // of understanding rather than a regular expression over the page.
        $a = random_int(2, 6);
        $b = random_int(2, 6);
        $text = (string) ($a + $b);
        $words = captcha_word($a) . ' qoʻshuv ' . captcha_word($b);
    }
    $challenge = ['text' => $text, 'words' => $words, 'born' => time(), 'tries' => 0];
    $_SESSION['captcha'] = $challenge;
    return $challenge;
}

/** 0–12 in Uzbek, which is as far as the sums go. */
function captcha_word(int $n): string
{
    $words = [
        'nol', 'bir', 'ikki', 'uch', 'toʻrt', 'besh', 'olti',
        'yetti', 'sakkiz', 'toʻqqiz', 'oʻn', 'oʻn bir', 'oʻn ikki',
    ];
    return $words[$n] ?? (string) $n;
}

/**
 * Check an answer, and burn the challenge whatever the result.
 *
 * Burnt on success so that one solved image cannot carry a run of password
 * guesses; counted on failure so that a loop answering at random is not free.
 */
function captcha_verify_builtin(array $config, string $given): bool
{
    $held = $_SESSION['captcha'] ?? null;
    if (!is_array($held) || !isset($held['text'], $held['born'])) {
        return false;
    }
    if ((int) $held['born'] + CAPTCHA_TTL < time()) {
        captcha_renew($config);
        return false;
    }
    $want = (string) $held['text'];
    $got = trim($given);
    $ok = strcasecmp($want, $got) === 0 && $got !== '';
    if ($ok) {
        unset($_SESSION['captcha']);
        return true;
    }
    $held['tries'] = (int) ($held['tries'] ?? 0) + 1;
    $_SESSION['captcha'] = $held;
    if ($held['tries'] >= CAPTCHA_MAX_TRIES) {
        captcha_renew($config);
    }
    return false;
}

/**
 * The challenge as a PNG.
 *
 * Deliberately plain: a wave of lines across five rotated characters on a
 * noisy field. It is not trying to beat a machine-learning model — nothing
 * drawn in GD does — it is trying to cost more than a form post, which is
 * what an unattended password list is made of.
 */
function captcha_png(array $config): string
{
    $challenge = captcha_challenge($config);
    $text = (string) $challenge['text'];

    $w = 190;
    $h = 64;
    $img = imagecreatetruecolor($w, $h);
    $paper = imagecolorallocate($img, 251, 247, 239);
    imagefilledrectangle($img, 0, 0, $w, $h, $paper);

    // Noise first, so the characters sit on top of it rather than under it.
    for ($i = 0; $i < 420; $i++) {
        $grey = imagecolorallocate($img, random_int(180, 230), random_int(180, 230), random_int(190, 235));
        imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $grey);
    }
    for ($i = 0; $i < 5; $i++) {
        $line = imagecolorallocate($img, random_int(150, 210), random_int(160, 210), random_int(180, 225));
        imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $line);
    }

    $x = 16;
    for ($i = 0, $n = strlen($text); $i < $n; $i++) {
        $ink = imagecolorallocate($img, random_int(20, 70), random_int(25, 80), random_int(60, 130));
        // Each character is drawn into a tile of its own and the tile is
        // turned, which is the only rotation GD's built-in font allows.
        $tile = imagecreatetruecolor(22, 34);
        imagefilledrectangle($tile, 0, 0, 22, 34, $paper);
        imagecolortransparent($tile, $paper);
        imagestring($tile, 5, 4, 8, $text[$i], $ink);
        $turned = imagerotate($tile, random_int(-26, 26), $paper);
        imagecolortransparent($turned, $paper);
        imagecopy($img, $turned, $x, random_int(8, 20), 0, 0, imagesx($turned), imagesy($turned));
        imagedestroy($tile);
        imagedestroy($turned);
        $x += random_int(30, 36);
    }

    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    imagedestroy($img);
    return $png;
}

/* -------------------------------------------------------------- turnstile */

/**
 * Ask Cloudflare whether that token is a person.
 *
 * Fails closed, and says which of the two it was: a token that came back
 * refused, or a Cloudflare that could not be reached at all. The second is
 * not the reader's fault and the login page says so rather than reading as a
 * wrong answer — an installation that loses its outbound route would
 * otherwise look exactly like one whose organiser is typing badly.
 *
 * @return array{0:bool,1:string} [passed, 'ok'|'bad'|'unreachable']
 */
function captcha_verify_turnstile(array $config, string $token, string $ip): array
{
    if (trim($token) === '') {
        return [false, 'bad'];
    }
    $body = http_build_query([
        'secret' => (string) $config['turnstile_secret'],
        'response' => $token,
        'remoteip' => $ip,
    ]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $answer = curl_exec($ch);
        curl_close($ch);
        $raw = is_string($answer) ? $answer : null;
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 6,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $answer = @file_get_contents($url, false, $context);
        $raw = is_string($answer) ? $answer : null;
    }

    if ($raw === null) {
        error_log('[forum-admin] turnstile unreachable');
        return [false, 'unreachable'];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [false, 'unreachable'];
    }
    return [!empty($data['success']), !empty($data['success']) ? 'ok' : 'bad'];
}

/**
 * The one call the login page makes.
 *
 * @return array{0:bool,1:string} [passed, 'ok'|'bad'|'unreachable']
 */
function captcha_verify(array $config, array $post, string $ip): array
{
    if (captcha_turnstile($config)) {
        return captcha_verify_turnstile(
            $config,
            is_string($post['cf-turnstile-response'] ?? null) ? $post['cf-turnstile-response'] : '',
            $ip,
        );
    }
    $given = is_string($post['captcha'] ?? null) ? $post['captcha'] : '';
    return [captcha_verify_builtin($config, $given), 'bad'];
}
