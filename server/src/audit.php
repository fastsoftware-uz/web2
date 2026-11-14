<?php
/**
 * What was done, by whom, and when.
 *
 * The admin can now change what the public site says and can add projects to
 * it. Once more than one person can do that, "the site says something wrong"
 * stops being answerable by looking at the site: somebody has to be able to
 * ask when it started saying it and who saved it. That is this file.
 *
 * Every action that changes anything is written here — a login, a failed
 * login, a status change, a field edited, a project added or hidden, a
 * photograph uploaded or deleted, an account created or given a different
 * role. Reading is not written down: a page view by a colleague is noise, and
 * noise is what makes a log nobody reads. The two exceptions are the ones
 * that take personal data off the server — the CSV export of the guest list
 * and of the log itself.
 *
 * ## Append-only, one file per month
 *
 * data/audit/2026-11.jsonl, one JSON object per line, opened with LOCK_EX for
 * the one line being appended — the same shape, and the same reasoning, as
 * the flat-file registration store. A month per file so that a forum that
 * runs for a year does not end with a single file nothing can open, and so
 * that an old month can be archived by moving one file.
 *
 * Nothing here is ever rewritten or deleted by the application. There is no
 * "clear the log" button on purpose: a log with one is a log that says
 * whatever the last person to use it wanted it to say.
 */

declare(strict_types=1);

/** Human names for what the `act` field holds, for the log's own page. */
const AUDIT_LABELS = [
    'login' => 'Kirdi',
    'login.failed' => 'Parol notoʻgʻri',
    'login.locked' => 'Bloklangan manzildan urinish',
    'login.captcha' => 'Tekshiruv savoli notoʻgʻri',
    'logout' => 'Chiqdi',
    'password.changed' => 'Oʻz parolini oʻzgartirdi',
    'registration.status' => 'Ariza holati oʻzgardi',
    'registration.ticket' => 'E-bilet berildi/bekor qilindi',
    'registration.export' => 'Arizalar CSV yuklab olindi',
    'site.save' => 'Sayt matni saqlandi',
    'site.revert' => 'Maydon asl holiga qaytarildi',
    'site.revert-all' => 'Barcha oʻzgarishlar bekor qilindi',
    'site.republish' => 'Sayt qayta nashr etildi',
    'site.hide' => 'Loyiha saytdan olindi',
    'site.show' => 'Loyiha saytga qaytarildi',
    'project.add' => 'Yangi loyiha qoʻshildi',
    'project.remove' => 'Qoʻshilgan loyiha oʻchirildi',
    'project.import' => 'Excel orqali loyihalar qoʻshildi',
    'photo.add' => 'Rasm yuklandi',
    'photo.remove' => 'Rasm oʻchirildi',
    'photo.order' => 'Rasmlar tartibi oʻzgardi',
    'user.add' => 'Foydalanuvchi qoʻshildi',
    'user.role' => 'Huquqi oʻzgartirildi',
    'user.password' => 'Paroli oʻzgartirildi',
    'user.active' => 'Yoqildi / oʻchirildi',
    'user.remove' => 'Foydalanuvchi oʻchirildi',
    'audit.export' => 'Jurnal CSV yuklab olindi',
];

function audit_dir(array $config): string
{
    return rtrim((string) $config['data_dir'], '/\\') . '/audit';
}

function audit_file(array $config, string $month): string
{
    return audit_dir($config) . '/' . $month . '.jsonl';
}

/**
 * Write one line.
 *
 * It never throws and it never prints. A log that can take a page down with
 * it is a log somebody will switch off; the failure goes to the PHP error log
 * and the action the operator asked for carries on.
 *
 * `$detail` is for the few words that make a line answerable — which field,
 * which project, from what to what. Values are cut short, because this is a
 * log and not a second copy of the data, and because a 1200-character note
 * pasted into a registration should not be in two files.
 */
function audit_log(array $config, string $action, array $detail = [], string $target = ''): void
{
    try {
        $dir = audit_dir($config);
        if (!is_dir($dir) && !@mkdir($dir, 0o770, true) && !is_dir($dir)) {
            return;
        }
        $user = $_SESSION['uid'] ?? '';
        $line = [
            't' => gmdate('c'),
            'uid' => is_string($user) ? $user : '',
            'login' => (string) ($_SESSION['ulogin'] ?? ''),
            'role' => (string) ($_SESSION['urole'] ?? ''),
            'ip' => function_exists('app_client_ip') ? app_client_ip() : '',
            'act' => $action,
            'target' => audit_cut($target, 120),
            'detail' => audit_clean($detail),
        ];
        $file = audit_file($config, gmdate('Y-m'));
        $json = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        @file_put_contents($file, $json . "\n", FILE_APPEND | LOCK_EX);
        @chmod($file, 0o640);
    } catch (Throwable $e) {
        error_log('[forum-audit] ' . $e->getMessage());
    }
}

function audit_cut(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (function_exists('mb_strlen') ? mb_strlen($value) <= $max : strlen($value) <= $max) {
        return $value;
    }
    $cut = function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    return $cut . '…';
}

/**
 * Make a detail array safe to keep.
 *
 * Two rules, both learnt the hard way elsewhere in this backend: a value is
 * cut to a length a log can carry, and anything whose key looks like a secret
 * is not written at all. The second is belt and braces — nothing calls this
 * with a password — but a log is exactly the file that outlives the code that
 * writes it.
 *
 * @return array<string,string>
 */
function audit_clean(array $detail): array
{
    $out = [];
    foreach ($detail as $k => $v) {
        $k = (string) $k;
        if (preg_match('/pass|parol|secret|hash|token|kalit/i', $k) === 1) {
            continue;
        }
        if (is_bool($v)) {
            $v = $v ? 'ha' : 'yoʻq';
        } elseif (is_array($v)) {
            $v = implode(', ', array_map(static fn($x) => is_scalar($x) ? (string) $x : '…', $v));
        } elseif ($v === null) {
            $v = '—';
        }
        $out[$k] = audit_cut((string) $v, 200);
    }
    return $out;
}

/** The months a log exists for, newest first. */
function audit_months(array $config): array
{
    $dir = audit_dir($config);
    if (!is_dir($dir)) {
        return [];
    }
    $months = [];
    foreach ((array) @scandir($dir) as $name) {
        if (is_string($name) && preg_match('/^(\d{4}-\d{2})\.jsonl$/', $name, $m) === 1) {
            $months[] = $m[1];
        }
    }
    rsort($months);
    return $months;
}

/**
 * Lines, newest first.
 *
 * Reads at most `$limit` lines, walking back through the months, so a log a
 * year long is not loaded into memory to show the last fifty entries. A
 * filter is applied as the lines are read for the same reason.
 *
 * @param array{month?:string,login?:string,act?:string,q?:string} $filter
 * @return array<int,array<string,mixed>>
 */
function audit_read(array $config, array $filter = [], int $limit = 300): array
{
    $months = audit_months($config);
    $wanted = (string) ($filter['month'] ?? '');
    if ($wanted !== '') {
        $months = in_array($wanted, $months, true) ? [$wanted] : [];
    }

    $login = app_lower(trim((string) ($filter['login'] ?? '')));
    $act = (string) ($filter['act'] ?? '');
    $needle = app_lower(trim((string) ($filter['q'] ?? '')));

    $out = [];
    foreach ($months as $month) {
        $file = audit_file($config, $month);
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $row = json_decode($lines[$i], true);
            if (!is_array($row) || !isset($row['act'])) {
                continue;
            }
            if ($login !== '' && app_lower((string) ($row['login'] ?? '')) !== $login) {
                continue;
            }
            if ($act !== '' && (string) $row['act'] !== $act) {
                continue;
            }
            if ($needle !== '') {
                $hay = app_lower(
                    (string) ($row['target'] ?? '') . ' ' .
                    implode(' ', array_map('strval', (array) ($row['detail'] ?? []))) . ' ' .
                    (string) ($row['ip'] ?? ''),
                );
                if (!str_contains($hay, $needle)) {
                    continue;
                }
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                return $out;
            }
        }
    }
    return $out;
}

/** How many lines the whole log holds. Cheap enough: it counts newlines. */
function audit_total(array $config): int
{
    $n = 0;
    foreach (audit_months($config) as $month) {
        $raw = @file_get_contents(audit_file($config, $month));
        if (is_string($raw)) {
            $n += substr_count($raw, "\n");
        }
    }
    return $n;
}

/** Every distinct login the log has seen, for the filter menu. */
function audit_actors(array $config): array
{
    $seen = [];
    foreach (array_slice(audit_months($config), 0, 3) as $month) {
        $lines = @file(audit_file($config, $month), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ((array) $lines as $line) {
            $row = json_decode($line, true);
            $who = is_array($row) ? (string) ($row['login'] ?? '') : '';
            if ($who !== '') {
                $seen[$who] = true;
            }
        }
    }
    $out = array_keys($seen);
    sort($out);
    return $out;
}
