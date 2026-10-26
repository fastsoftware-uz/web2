<?php
/**
 * Where registrations are kept, and the only code that touches them.
 *
 * SQLite when the server has pdo_sqlite, and an append-only JSON-lines file
 * when it does not. Alpine ships PHP's extensions as separate apk packages
 * and php84-pdo_sqlite is frequently not among the ones installed, so a
 * backend that assumes it is a backend that 500s on arrival. The two stores
 * are behind the same five functions and the rest of the application cannot
 * tell which one it has.
 *
 * Both are safe to write from several PHP-FPM workers at once: SQLite takes
 * its own lock, and the JSON-lines file is opened with LOCK_EX for the one
 * line being appended. Neither is safe to edit by hand while the site is up.
 */

declare(strict_types=1);

/**
 * True when this installation is using SQLite rather than the flat file.
 *
 * `auto` is the right answer everywhere: it takes SQLite when the extension
 * is there and the flat file when it is not. `jsonl` is for somebody who
 * would rather be able to read the registrations with `cat` and `wc -l`
 * than install an extension, which on a forum's guest list is a defensible
 * preference, and it is also how this code path is tested.
 */
function storage_is_sqlite(): bool
{
    static $yes = null;
    if ($yes === null) {
        $mode = (string) (app_config()['storage'] ?? 'auto');
        $can = class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
        $yes = $mode === 'jsonl' ? false : ($mode === 'sqlite' ? $can : $can);
    }
    return $yes;
}

function storage_describe(): string
{
    if (storage_is_sqlite()) {
        return 'SQLite (pdo_sqlite)';
    }
    // Two different reasons to be on the flat file, and an installation that
    // chose it should not be told its extension is missing.
    $chosen = (string) (app_config()['storage'] ?? 'auto') === 'jsonl';
    return $chosen ? 'JSON lines (sozlamada tanlangan)' : 'JSON lines (pdo_sqlite not installed)';
}

function storage_dir(array $config): string
{
    $dir = $config['data_dir'];
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException("Data directory is missing and cannot be created: {$dir}");
    }
    if (!is_writable($dir)) {
        throw new RuntimeException("Data directory is not writable by the PHP user: {$dir}");
    }
    return $dir;
}

function storage_pdo(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . storage_dir($config) . '/registrations.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Survives a power cut mid-write and lets a reader read while the form
    // is being submitted, which the admin page does constantly.
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 4000');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS registrations (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at    TEXT NOT NULL,
            role          TEXT NOT NULL,
            full_name     TEXT NOT NULL,
            organisation  TEXT NOT NULL,
            position      TEXT NOT NULL,
            country       TEXT NOT NULL,
            email         TEXT NOT NULL,
            phone         TEXT NOT NULL,
            direction     TEXT NOT NULL DEFAULT "",
            note          TEXT NOT NULL DEFAULT "",
            lang          TEXT NOT NULL DEFAULT "uz",
            status        TEXT NOT NULL DEFAULT "new",
            ip            TEXT NOT NULL DEFAULT ""
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS reg_created ON registrations (created_at DESC)');
    return $pdo;
}

function storage_jsonl_path(array $config): string
{
    return storage_dir($config) . '/registrations.jsonl';
}

/**
 * Appends one registration and returns its id.
 *
 * The row is written exactly as it will be read back, so the two stores
 * cannot drift apart in the shape they hand to the admin page.
 */
function storage_add(array $config, array $row): int
{
    if (storage_is_sqlite()) {
        $pdo = storage_pdo($config);
        $sql = 'INSERT INTO registrations
                (created_at, role, full_name, organisation, position, country,
                 email, phone, direction, note, lang, status, ip)
                VALUES (:created_at, :role, :full_name, :organisation, :position, :country,
                        :email, :phone, :direction, :note, :lang, :status, :ip)';
        $pdo->prepare($sql)->execute($row);
        return (int) $pdo->lastInsertId();
    }

    $path = storage_jsonl_path($config);
    /**
     * Opened read-and-append rather than append-only, and counted through
     * the handle we are already holding.
     *
     * The first version counted the lines by opening the file a second time.
     * Under the exclusive lock that second open fails outright on Windows and
     * is a race everywhere else: two registrations arriving together both
     * read "no lines" and were both written as id 1, which the admin's status
     * form would then apply to whichever it found first. Counting inside the
     * lock, on the locked handle, is the only version of this that is right.
     */
    $handle = fopen($path, 'c+b');
    if ($handle === false) {
        throw new RuntimeException("Cannot open the registrations file: {$path}");
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Cannot lock the registrations file');
        }
        $id = 0;
        rewind($handle);
        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $id++;
            }
        }
        $id++;
        $row['id'] = $id;
        fseek($handle, 0, SEEK_END);
        fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fflush($handle);
        flock($handle, LOCK_UN);
        @chmod($path, 0660);
        return $id;
    } finally {
        fclose($handle);
    }
}

/** Every registration, newest first. */
function storage_all(array $config): array
{
    if (storage_is_sqlite()) {
        return storage_pdo($config)
            ->query('SELECT * FROM registrations ORDER BY id DESC')
            ->fetchAll();
    }

    $path = storage_jsonl_path($config);
    if (!is_file($path)) {
        return [];
    }
    $rows = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = json_decode($line, true);
        // A half-written line from a crash is skipped rather than fatal: one
        // lost registration is better than an admin page that will not open.
        if (is_array($row) && isset($row['id'])) {
            $rows[] = $row;
        }
    }
    return array_reverse($rows);
}

/**
 * Marks a registration new / confirmed / declined.
 *
 * In the flat-file store the whole file is rewritten, which is fine at the
 * scale a forum registers at and is the only way to change a line in place.
 */
function storage_set_status(array $config, int $id, string $status): bool
{
    if (!in_array($status, ['new', 'confirmed', 'declined'], true)) {
        return false;
    }

    if (storage_is_sqlite()) {
        $stmt = storage_pdo($config)->prepare('UPDATE registrations SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    $path = storage_jsonl_path($config);
    if (!is_file($path)) {
        return false;
    }
    $handle = fopen($path, 'c+b');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if ($handle) {
            fclose($handle);
        }
        return false;
    }
    try {
        $out = '';
        $hit = false;
        rewind($handle);
        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (is_array($row) && (int) ($row['id'] ?? 0) === $id) {
                $row['status'] = $status;
                $hit = true;
                $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            }
            $out .= $line;
        }
        if ($hit) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $out);
            fflush($handle);
        }
        return $hit;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * How many registrations this address has sent in the last hour.
 *
 * Kept beside the data rather than in a session, because the thing being
 * limited is a form that nobody has to log in to use.
 */
function storage_recent_from_ip(array $config, string $ip): int
{
    $cutoff = gmdate('c', time() - 3600);
    if (storage_is_sqlite()) {
        $stmt = storage_pdo($config)->prepare(
            'SELECT COUNT(*) FROM registrations WHERE ip = ? AND created_at > ?'
        );
        $stmt->execute([$ip, $cutoff]);
        return (int) $stmt->fetchColumn();
    }
    $n = 0;
    foreach (storage_all($config) as $row) {
        if (($row['ip'] ?? '') === $ip && ($row['created_at'] ?? '') > $cutoff) {
            $n++;
        }
    }
    return $n;
}
