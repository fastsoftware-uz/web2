<?php
/**
 * The one public endpoint: POST a registration.
 *
 *   POST /api.php      Content-Type: application/json
 *   {role, full_name, organisation, position, country, email, phone,
 *    direction?, note?, lang?}
 *
 * Answers 200 {ok:true,id} or 422 {ok:false,fields:[…]}. It never explains
 * in prose: the page that collected the answers knows the reader's language
 * and this does not, so it names the fields and lets the page speak.
 *
 * A GET returns a small health object, which is the quickest way to tell
 * from a browser that nginx, PHP and the data directory are all talking to
 * each other.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/app.php';
require_once __DIR__ . '/../src/ticket.php';

$config = app_config();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/**
 * CORS, only for the origins the configuration names.
 *
 * With nginx serving the built site and this file from the same host the
 * list is empty, no headers are sent and none are needed. The list exists
 * for the window where the site is still on GitHub Pages.
 */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $config['allowed_origins'] ?? [];
if ($origin !== '' && $allowed !== []) {
    if (!in_array($origin, $allowed, true)) {
        app_json(403, ['ok' => false, 'error' => 'origin']);
        exit;
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Max-Age: 86400');
}

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    $ready = true;
    $detail = 'ok';
    try {
        storage_dir($config);
    } catch (Throwable $e) {
        $ready = false;
        $detail = $e->getMessage();
    }
    app_json($ready ? 200 : 500, [
        'ok' => $ready,
        'store' => storage_describe(),
        'detail' => $detail,
    ]);
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST, OPTIONS');
    app_json(405, ['ok' => false, 'error' => 'method']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
// A form post is accepted as well as JSON, so the page still works if its
// JavaScript never ran.
$input = str_starts_with(trim($raw), '{')
    ? (json_decode($raw, true) ?: [])
    : $_POST;
if (!is_array($input)) {
    app_json(400, ['ok' => false, 'error' => 'body']);
    exit;
}

/**
 * The honeypot.
 *
 * The form carries a field no human sees and no human fills. A bot that
 * fills every input it finds is answered 200 and nothing is stored: told it
 * failed, it would come back having learned something.
 */
if (trim((string) ($input['website'] ?? '')) !== '') {
    app_json(200, ['ok' => true, 'id' => 0]);
    exit;
}

$ip = app_client_ip();
[$row, $bad] = app_validate($input, $ip);

if ($bad !== []) {
    app_json(422, ['ok' => false, 'fields' => array_values(array_unique($bad))]);
    exit;
}

try {
    $limit = (int) ($config['rate_limit_per_hour'] ?? 20);
    if ($limit > 0 && $ip !== '' && storage_recent_from_ip($config, $ip) >= $limit) {
        app_json(429, ['ok' => false, 'error' => 'rate']);
        exit;
    }
    $id = storage_add($config, $row);
} catch (Throwable $e) {
    // The reader is told the server failed, not how. The detail goes to the
    // PHP error log, which is where somebody who can fix it will look.
    error_log('[forum-register] ' . $e->getMessage());
    app_json(500, ['ok' => false, 'error' => 'store']);
    exit;
}

/**
 * The ticket goes back with the answer.
 *
 * It is issued on registration rather than on approval, which the client
 * chose: a guest who has just filled in a form leaves with something in their
 * hand. Approval is still what the door reads — the badge carries the status,
 * and a registration the organisers reject says so on its own face.
 *
 * A ticket that cannot be signed must not fail a registration that is already
 * stored, so the whole thing is wrapped: the guest is then simply told the
 * registration arrived, and the organisers hand the ticket over from the
 * admin afterwards.
 */
$ticket = null;
try {
    $ticket = ['number' => ticket_number($id, $config), 'url' => ticket_url($id, $config)];
} catch (Throwable $e) {
    error_log('[forum-register] ticket: ' . $e->getMessage());
}

/**
 * The optional notification. A mail that cannot be sent must never fail a
 * registration that is already safely stored, so it is last and it is
 * wrapped.
 */
$notify = (string) ($config['notify_email'] ?? '');
if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
    try {
        $lines = ["#{$id} — {$row['role']}"];
        if ($ticket !== null) {
            $lines[] = 'e-ticket: ' . $ticket['url'];
        }
        foreach (['full_name', 'organisation', 'position', 'country', 'email', 'phone', 'direction', 'note'] as $f) {
            if ($row[$f] !== '') {
                $lines[] = "{$f}: {$row[$f]}";
            }
        }
        @mail(
            $notify,
            'Forum registration #' . $id . ' — ' . $row['full_name'],
            implode("\n", $lines),
            "Content-Type: text/plain; charset=utf-8\r\nX-Mailer: forum-register"
        );
    } catch (Throwable $e) {
        error_log('[forum-register] mail: ' . $e->getMessage());
    }
}

app_json(200, ['ok' => true, 'id' => $id] + ($ticket === null ? [] : ['ticket' => $ticket]));
