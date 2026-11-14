<?php
/**
 * Who may open the admin, and what each of them may do.
 *
 * The admin began as one password. That is still how an installation starts:
 * .env carries a hash, and the person who knows it is the owner. But the
 * forum is run by more than one person — somebody keeps the guest list,
 * somebody else corrects the site's text — and "give them the password" is
 * the answer that makes an audit log meaningless, because every line in it
 * would say the same name.
 *
 * So there are accounts. The owner makes them, each has a role, and every
 * action is written down under the name that performed it (see audit.php).
 *
 * ## Nothing to do on an existing server
 *
 * data/users.json is written the first time an account is added. Until then
 * there is no file, and users_all() answers with one account synthesised from
 * the password the installation already has: login `admin`, role `owner`. An
 * installation that updates to this version therefore keeps working with the
 * password it had, and nobody has to edit anything on the server.
 *
 * The moment the file exists it is the whole truth, and the legacy password
 * lives on inside the owner account that was written into it.
 */

declare(strict_types=1);

require_once __DIR__ . '/guard.php';

/**
 * The three roles, and what each may do.
 *
 * Deliberately three and not a permission matrix on screen: the person
 * handing out access is an organiser between two meetings, and a screen of
 * fourteen checkboxes is a screen on which somebody ticks the wrong one.
 *
 *   owner   — everything, including making accounts and exporting the
 *             guest list with its telephone numbers.
 *   editor  — may change what the site says, add projects, upload photographs
 *             and move a registration between new/confirmed/declined.
 *             May not export personal data and may not touch accounts.
 *   viewer  — reads. Every form is closed to them.
 *
 * The export is the one thing kept from an editor on purpose: the CSV is the
 * whole guest list, telephone numbers and e-mail addresses together, in a
 * file that leaves the server. That is the owner's to hand out.
 */
const USER_ROLES = ['owner', 'editor', 'viewer'];

const ROLE_LABELS = [
    'owner' => 'Egasi — hamma narsa',
    'editor' => 'Muharrir — saytni tahrirlaydi',
    'viewer' => 'Kuzatuvchi — faqat koʻradi',
];

const ROLE_CAPS = [
    'owner' => [
        'registrations.view', 'registrations.edit', 'registrations.export',
        'site.edit', 'projects.add', 'projects.photos', 'projects.import',
        'users.manage', 'audit.view', 'password.change',
    ],
    'editor' => [
        'registrations.view', 'registrations.edit',
        'site.edit', 'projects.add', 'projects.photos', 'projects.import',
        'audit.view', 'password.change',
    ],
    /*
     * A viewer reads the guest list and nothing else.
     *
     * The log is deliberately not theirs: it carries every other person's
     * login name and the address they worked from, which is a list of the
     * organisation's own staff and is of no use to somebody whose job is to
     * look at who has registered. The owner and the editors — the people
     * whose own actions are in it — can read it.
     */
    'viewer' => [
        'registrations.view', 'password.change',
    ],
];

function users_file(array $config): string
{
    return rtrim((string) $config['data_dir'], '/\\') . '/users.json';
}

/**
 * Every account, as stored.
 *
 * When the file does not exist yet this is the installation's own password
 * wearing an account's clothes. It is not written anywhere — writing it would
 * freeze a password that .env is still allowed to change — and it carries the
 * `legacy` mark so that the places that must know can tell.
 *
 * @return array<int,array<string,mixed>>
 */
function users_all(array $config): array
{
    $file = users_file($config);
    $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    $rows = is_array($data['users'] ?? null) ? $data['users'] : null;

    if ($rows === null) {
        $hash = guard_password_hash($config);
        return [[
            'id' => 'owner',
            'login' => 'admin',
            'name' => 'Bosh admin',
            'role' => 'owner',
            'hash' => $hash,
            'active' => $hash !== '',
            'legacy' => true,
            /* One-based, like every account written into the file, so that
               "how many times has this password changed" counts the same
               whether or not the installation has accounts yet. */
            'pwv' => guard_password_version($config) + 1,
            'created_at' => '',
            'created_by' => '',
            'last_seen' => '',
        ]];
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['login'])) {
            continue;
        }
        $role = (string) ($row['role'] ?? 'viewer');
        $out[] = [
            'id' => (string) ($row['id'] ?? $row['login']),
            'login' => (string) $row['login'],
            'name' => (string) ($row['name'] ?? $row['login']),
            'role' => in_array($role, USER_ROLES, true) ? $role : 'viewer',
            'hash' => (string) ($row['hash'] ?? ''),
            'active' => (bool) ($row['active'] ?? true),
            'legacy' => false,
            'pwv' => (int) ($row['pwv'] ?? 1),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'created_by' => (string) ($row['created_by'] ?? ''),
            'last_seen' => (string) ($row['last_seen'] ?? ''),
        ];
    }
    return $out;
}

/** True once accounts are kept in a file of their own. */
function users_installed(array $config): bool
{
    return is_file(users_file($config));
}

/**
 * Write the accounts.
 *
 * Same temporary-file-then-rename as everything else that writes here: a
 * reader must never catch a half-written list of the people who may log in.
 *
 * @param array<int,array<string,mixed>> $users
 */
function users_save(array $config, array $users): void
{
    $file = users_file($config);
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0o770, true) && !is_dir($dir)) {
        throw new RuntimeException("Maʼlumotlar papkasini yaratib boʻlmadi: {$dir}");
    }
    $clean = [];
    foreach ($users as $u) {
        $clean[] = [
            'id' => (string) $u['id'],
            'login' => (string) $u['login'],
            'name' => (string) $u['name'],
            'role' => (string) $u['role'],
            'hash' => (string) $u['hash'],
            'active' => (bool) $u['active'],
            'pwv' => (int) ($u['pwv'] ?? 1),
            'created_at' => (string) ($u['created_at'] ?? ''),
            'created_by' => (string) ($u['created_by'] ?? ''),
            'last_seen' => (string) ($u['last_seen'] ?? ''),
        ];
    }
    $json = json_encode(
        ['version' => 1, 'users' => $clean],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    if ($json === false) {
        throw new RuntimeException('Foydalanuvchilarni yozib boʻlmadi.');
    }
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException("Yozib boʻlmadi: {$file}");
    }
    @chmod($tmp, 0o600);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException("Oʻrniga qoʻyib boʻlmadi: {$file}");
    }
}

/**
 * The first write: turn the installation's single password into an account.
 *
 * Called the moment the owner adds a second person. The owner's own hash is
 * copied out of wherever it currently lives, so the password they are logged
 * in with right now goes on working.
 */
function users_materialise(array $config): array
{
    if (users_installed($config)) {
        return users_all($config);
    }
    $users = users_all($config);
    $users[0]['legacy'] = false;
    $users[0]['created_at'] = gmdate('c');
    $users[0]['created_by'] = 'oʻrnatish';
    users_save($config, $users);
    return users_all($config);
}

/** One account by login, case-insensitively. */
function users_find(array $config, string $login): ?array
{
    $login = app_lower(trim($login));
    foreach (users_all($config) as $u) {
        if (app_lower($u['login']) === $login) {
            return $u;
        }
    }
    return null;
}

function users_by_id(array $config, string $id): ?array
{
    foreach (users_all($config) as $u) {
        if ($u['id'] === $id) {
            return $u;
        }
    }
    return null;
}

/** How many owners can still log in. Used to refuse removing the last one. */
function users_owner_count(array $config): int
{
    $n = 0;
    foreach (users_all($config) as $u) {
        if ($u['role'] === 'owner' && $u['active'] && $u['hash'] !== '') {
            $n++;
        }
    }
    return $n;
}

/** Is this login free, and does it look like one? */
function users_login_complaint(array $config, string $login, string $exceptId = ''): string
{
    $login = trim($login);
    if (preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $login) !== 1) {
        return 'shape';
    }
    foreach (users_all($config) as $u) {
        if ($u['id'] !== $exceptId && app_lower($u['login']) === app_lower($login)) {
            return 'taken';
        }
    }
    return '';
}

/**
 * Add an account.
 *
 * @return array<string,mixed> the account as stored
 */
function users_add(array $config, string $login, string $name, string $role, string $plain, string $by): array
{
    $users = users_materialise($config);
    $users[] = [
        'id' => bin2hex(random_bytes(8)),
        'login' => trim($login),
        'name' => trim($name) !== '' ? trim($name) : trim($login),
        'role' => in_array($role, USER_ROLES, true) ? $role : 'viewer',
        'hash' => password_hash($plain, PASSWORD_DEFAULT),
        'active' => true,
        'pwv' => 1,
        'created_at' => gmdate('c'),
        'created_by' => $by,
        'last_seen' => '',
    ];
    users_save($config, $users);
    return $users[count($users) - 1];
}

/**
 * Change one account, through a callback that receives and returns it.
 *
 * Every change bumps `pwv` when the callback says to, which is what ends that
 * person's other sessions — see guard_admin_session().
 *
 * @param callable(array<string,mixed>):array<string,mixed> $change
 */
function users_update(array $config, string $id, callable $change): void
{
    $users = users_materialise($config);
    $hit = false;
    foreach ($users as $i => $u) {
        if ($u['id'] === $id) {
            $users[$i] = $change($u);
            $hit = true;
        }
    }
    if (!$hit) {
        throw new RuntimeException('Bunday foydalanuvchi yoʻq.');
    }
    users_save($config, $users);
}

function users_remove(array $config, string $id): void
{
    $users = users_materialise($config);
    $left = array_values(array_filter($users, static fn(array $u): bool => $u['id'] !== $id));
    if (count($left) === count($users)) {
        throw new RuntimeException('Bunday foydalanuvchi yoʻq.');
    }
    users_save($config, $left);
}

/**
 * The account this session belongs to, or null.
 *
 * A session opened before accounts existed carries no id: it is the owner,
 * which is who it was when it logged in.
 */
function users_current(array $config): ?array
{
    if (empty($_SESSION['admin'])) {
        return null;
    }
    $id = (string) ($_SESSION['uid'] ?? '');
    $user = $id === '' ? null : users_by_id($config, $id);
    if ($user === null) {
        $user = users_find($config, 'admin');
    }
    if ($user === null || !$user['active']) {
        return null;
    }
    return $user;
}

/** May this account do that? */
function user_can(?array $user, string $cap): bool
{
    if ($user === null || !$user['active']) {
        return false;
    }
    return in_array($cap, ROLE_CAPS[$user['role']] ?? [], true);
}

/** The owner's own hash, for the places that still ask for "the password". */
function users_set_password(array $config, string $id, string $plain): void
{
    users_update($config, $id, static function (array $u) use ($plain): array {
        $u['hash'] = password_hash($plain, PASSWORD_DEFAULT);
        $u['pwv'] = (int) $u['pwv'] + 1;
        return $u;
    });
}

/**
 * Note that this account was seen, at most once an hour.
 *
 * It is written so the owner can tell a dormant account from a working one
 * before deciding to remove it. Rewriting the file on every request would be
 * a write per page view for a fact nobody reads that often.
 */
function users_touch(array $config, string $id): void
{
    if (!users_installed($config)) {
        return;
    }
    $user = users_by_id($config, $id);
    if ($user === null) {
        return;
    }
    if ($user['last_seen'] !== '' && strtotime($user['last_seen']) > time() - 3600) {
        return;
    }
    try {
        users_update($config, $id, static function (array $u): array {
            $u['last_seen'] = gmdate('c');
            return $u;
        });
    } catch (Throwable) {
        /* A note that could not be written must never fail a page. */
    }
}
