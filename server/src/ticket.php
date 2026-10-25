<?php
/**
 * The e-ticket: its number, its address, and the registration behind it.
 *
 * A ticket number is the registration's id with a signature on the end:
 *
 *     TITF26-000417-K7F3QA
 *
 * The signature is six characters of an HMAC over the id, keyed with a
 * secret that lives in the data directory and nowhere else. It is there for
 * one reason: the id is a counter, so a ticket address without it is an
 * address anybody can walk — #417 is between #416 and #418, and every guest's
 * name, organisation and badge would be a URL away. With it, a guessed
 * address is one of a billion.
 *
 * It is not a password and does not pretend to be one. Anyone holding the
 * ticket can open it, which is the point of sending it to them; the page it
 * opens shows the badge and the status, never the e-mail address, the phone
 * number or the note the guest wrote.
 */

declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/storage.php';

/** The prefix every number carries, so a number read aloud is placeable. */
const TICKET_PREFIX = 'TITF26';

/**
 * The alphabet the signature is written in: Crockford's base 32, which drops
 * I, L, O and U so that a number copied off a printed badge by hand cannot
 * turn a 1 into an I or a 0 into an O.
 */
const TICKET_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/**
 * The key the numbers are signed with.
 *
 * Generated on first use and kept in the data directory beside the
 * registrations, which is already outside the web root and already the one
 * directory PHP-FPM can write. Deleting it invalidates every ticket issued
 * so far, which is also the way to invalidate them all deliberately.
 */
function ticket_secret(array $config): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $path = storage_dir($config) . '/ticket-secret.key';
    if (is_file($path)) {
        $read = trim((string) file_get_contents($path));
        if ($read !== '') {
            return $secret = $read;
        }
    }
    $new = bin2hex(random_bytes(32));
    // x: fails rather than overwrites, so two requests arriving together
    // cannot leave one of them signing with a key that was replaced.
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        $read = is_file($path) ? trim((string) file_get_contents($path)) : '';
        if ($read === '') {
            throw new RuntimeException('Cannot create the ticket key: ' . $path);
        }
        return $secret = $read;
    }
    fwrite($handle, $new);
    fclose($handle);
    @chmod($path, 0600);
    return $secret = $new;
}

/** Six characters of signature for one registration. */
function ticket_sign(int $id, array $config): string
{
    $raw = hash_hmac('sha256', TICKET_PREFIX . ':' . $id, ticket_secret($config), true);
    $out = '';
    for ($i = 0; $i < 6; $i++) {
        $out .= TICKET_ALPHABET[ord($raw[$i]) & 31];
    }
    return $out;
}

/** The whole number, as it is printed. */
function ticket_number(int $id, array $config): string
{
    return TICKET_PREFIX . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT) . '-' . ticket_sign($id, $config);
}

/**
 * The registration id a number names, or null.
 *
 * Case and stray spaces are forgiven, because this number is going to be
 * typed in off a sheet of paper at a door. Nothing else is: a number whose
 * signature does not check is not a ticket.
 */
function ticket_id(string $number, array $config): ?int
{
    $clean = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $number) ?? '');
    if (preg_match('/^' . TICKET_PREFIX . '-(\d{1,9})-([0-9A-Z]{6})$/', $clean, $m) !== 1) {
        return null;
    }
    $id = (int) $m[1];
    if ($id < 1) {
        return null;
    }
    return hash_equals(ticket_sign($id, $config), $m[2]) ? $id : null;
}

/**
 * The site's own address, for the link inside the QR code.
 *
 * `site_url` in the configuration when it is set — a mailed ticket has to
 * carry an address that works from outside, and the request this is answering
 * may be one from inside. Otherwise the request's own host, which is right
 * everywhere else and needs no setting up.
 */
function ticket_base(array $config): string
{
    $configured = rtrim((string) ($config['site_url'] ?? ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    $proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($https ? 'https' : 'http'));
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $proto . '://' . $host;
}

/** Where the badge is, and where the scanner lands. */
function ticket_url(int $id, array $config, bool $scan = false): string
{
    return ticket_base($config) . '/ticket/' . ticket_number($id, $config) . ($scan ? '?scan=1' : '');
}

/** The registration a ticket belongs to, or null if it is gone. */
function ticket_row(array $config, int $id): ?array
{
    foreach (storage_all($config) as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }
    return null;
}

/**
 * What a badge says, in the two languages it is printed in.
 *
 * Only what belongs on a badge. The e-mail address, the telephone number, the
 * note and the address the registration came from stay in the admin: a badge
 * is worn in a room full of strangers and photographed by half of them.
 */
function ticket_badge(array $row, array $config): array
{
    $role = (string) ($row['role'] ?? 'guest');
    $status = (string) ($row['status'] ?? 'new');
    return [
        'id' => (int) $row['id'],
        'number' => ticket_number((int) $row['id'], $config),
        'name' => (string) ($row['full_name'] ?? ''),
        'position' => (string) ($row['position'] ?? ''),
        'organisation' => (string) ($row['organisation'] ?? ''),
        'country' => (string) ($row['country'] ?? ''),
        'role' => $role,
        'role_uz' => $role === 'speaker' ? 'SPIKER' : 'MEHMON',
        'role_en' => $role === 'speaker' ? 'SPEAKER' : 'GUEST',
        'status' => $status,
        // 'declined' is the admin's own word for it — see storage_set_status,
        // which is the only place a status is ever written.
        'valid' => $status !== 'declined',
    ];
}
