<?php
/**
 * The admin: log in, read the registrations, change a status, export a CSV.
 *
 * Server-rendered on purpose. It is a table behind a password on somebody
 * else's server; a build step and a bundle would be two more things that can
 * be out of date when a colleague opens it to check who has registered.
 *
 * Everything is one file so that deploying it is copying a directory.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/app.php';

$config = app_config();

session_name('forum_admin');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    // Set over HTTPS only when the request arrived over HTTPS, so the cookie
    // still works while the site is being set up on plain HTTP.
    'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    'path' => '/',
]);
session_start();

$hash = (string) ($config['admin_password_hash'] ?? '');
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

/* ------------------------------------------------------------------ auth */

function admin_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf'];
}

function admin_check_token(): bool
{
    return isset($_SESSION['csrf'])
        && is_string($_POST['token'] ?? null)
        && hash_equals((string) $_SESSION['csrf'], (string) $_POST['token']);
}

$error = '';

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: ?');
    exit;
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $given = (string) ($_POST['password'] ?? '');
    if ($hash === '') {
        $error = 'no-hash';
    } elseif (password_verify($given, $hash)) {
        // A fresh id after a successful login, so a session fixed before it
        // is not the session that ends up logged in.
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: ?');
        exit;
    } else {
        // Costs the same whether the password was close or nothing like it.
        usleep(400000);
        $error = 'bad';
    }
}

$authed = !empty($_SESSION['admin']);

/* --------------------------------------------------------------- actions */

if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'status') {
    if (admin_check_token()) {
        storage_set_status($config, (int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
    }
    header('Location: ?' . ($_POST['back'] ?? ''));
    exit;
}

if ($authed && $action === 'csv') {
    $rows = admin_filter(storage_all($config), $_GET);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrations-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    // Excel reads a UTF-8 CSV as the local codepage unless the file opens
    // with a byte-order mark, and the names here are Cyrillic and Uzbek
    // Latin. Without this the client opens the export and sees mojibake.
    fwrite($out, "\xEF\xBB\xBF");
    // The escape argument is given explicitly: PHP 8.4 deprecates leaving it
    // out and prints the notice into the response, which lands inside the
    // CSV and corrupts it. An empty escape is also the correct one — PHP's
    // backslash escaping is not in RFC 4180 and confuses every other reader.
    fputcsv($out, FIELDS, ';', '"', '');
    foreach ($rows as $row) {
        $line = [];
        foreach (FIELDS as $f) {
            $line[] = (string) ($row[$f] ?? '');
        }
        fputcsv($out, $line, ';', '"', '');
    }
    fclose($out);
    exit;
}

/* -------------------------------------------------------------- filtering */

/** Search, role and status, applied in one place so the CSV matches the table. */
function admin_filter(array $rows, array $q): array
{
    $needle = app_lower(trim((string) ($q['q'] ?? '')));
    $role = (string) ($q['role'] ?? '');
    $status = (string) ($q['status'] ?? '');

    return array_values(array_filter($rows, static function (array $row) use ($needle, $role, $status): bool {
        if ($role !== '' && ($row['role'] ?? '') !== $role) {
            return false;
        }
        if ($status !== '' && ($row['status'] ?? 'new') !== $status) {
            return false;
        }
        if ($needle === '') {
            return true;
        }
        $hay = app_lower(implode(' ', [
            $row['full_name'] ?? '', $row['organisation'] ?? '', $row['position'] ?? '',
            $row['country'] ?? '', $row['email'] ?? '', $row['phone'] ?? '', $row['note'] ?? '',
        ]));
        return str_contains($hay, $needle);
    }));
}

$all = [];
$rows = [];
$fatal = '';
if ($authed) {
    try {
        $all = storage_all($config);
        $rows = admin_filter($all, $_GET);
    } catch (Throwable $e) {
        $fatal = $e->getMessage();
    }
}

$counts = ['all' => count($all), 'guest' => 0, 'speaker' => 0, 'new' => 0, 'confirmed' => 0, 'declined' => 0];
foreach ($all as $row) {
    $r = (string) ($row['role'] ?? '');
    $s = (string) ($row['status'] ?? 'new');
    if (isset($counts[$r])) {
        $counts[$r]++;
    }
    if (isset($counts[$s])) {
        $counts[$s]++;
    }
}

$query = http_build_query(array_filter([
    'q' => $_GET['q'] ?? '',
    'role' => $_GET['role'] ?? '',
    'status' => $_GET['status'] ?? '',
]));

$ROLE_LABEL = ['guest' => 'Mehmon', 'speaker' => 'Spiker'];
/**
 * Short names for the six directions.
 *
 * Written here rather than read from the site's forum-2026.json: the admin
 * runs on the organiser's own server and must not depend on a file that
 * lives with the published site. They are deliberately shorter than the
 * site's own headings, because this is a table column and not a card.
 */
$DIRECTION_LABEL = [
    'infrastructure' => 'Mehmonxona va infratuzilma',
    'heritage'       => 'Madaniy meros',
    'eco'            => 'Ekologik va ekstremal',
    'wellness'       => 'Tibbiy va wellness',
    'leisure'        => 'Koʻngilochar va oilaviy',
    'transport'      => 'Transport',
];
$STATUS_LABEL = ['new' => 'Yangi', 'confirmed' => 'Tasdiqlangan', 'declined' => 'Rad etilgan'];
?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Roʻyxatdan oʻtganlar — <?= app_h($config['site_name'] ?? 'Forum') ?></title>
<link rel="stylesheet" href="admin.css">
</head>
<body>

<?php if (!$authed): ?>
<main class="gate">
  <form method="post" class="card">
    <h1>Adminka</h1>
    <p class="muted"><?= app_h($config['site_name'] ?? '') ?></p>
    <input type="hidden" name="action" value="login">
    <label for="pw">Parol</label>
    <input id="pw" name="password" type="password" autocomplete="current-password" required autofocus>
    <?php if ($error === 'bad'): ?>
      <p class="err">Parol notoʻgʻri.</p>
    <?php elseif ($error === 'no-hash'): ?>
      <p class="err">
        Parol hali oʻrnatilmagan. <code>src/config.php</code> faylida
        <code>admin_password_hash</code> ni toʻldiring —
        <code>php -r 'echo password_hash("parol", PASSWORD_DEFAULT);'</code>
      </p>
    <?php endif; ?>
    <button type="submit">Kirish</button>
  </form>
</main>

<?php else: ?>
<header class="bar">
  <div>
    <strong>Roʻyxatdan oʻtganlar</strong>
    <span class="muted"><?= app_h($config['site_name'] ?? '') ?></span>
  </div>
  <div class="bar__right">
    <span class="muted store"><?= app_h(storage_describe()) ?></span>
    <a class="btn" href="?action=csv&amp;<?= app_h($query) ?>">CSV yuklab olish</a>
    <a class="btn btn--quiet" href="site.php">Saytni tahrirlash</a>
    <a class="btn btn--quiet" href="?action=logout">Chiqish</a>
  </div>
</header>

<main>
  <?php if ($fatal !== ''): ?>
    <p class="err big">Maʼlumotlarni oʻqib boʻlmadi: <?= app_h($fatal) ?></p>
  <?php endif; ?>

  <section class="tiles">
    <a class="tile <?= ($_GET['role'] ?? '') === '' && ($_GET['status'] ?? '') === '' ? 'tile--on' : '' ?>" href="?">
      <b><?= $counts['all'] ?></b><span>Jami</span>
    </a>
    <a class="tile <?= ($_GET['role'] ?? '') === 'guest' ? 'tile--on' : '' ?>" href="?role=guest">
      <b><?= $counts['guest'] ?></b><span>Mehmon</span>
    </a>
    <a class="tile <?= ($_GET['role'] ?? '') === 'speaker' ? 'tile--on' : '' ?>" href="?role=speaker">
      <b><?= $counts['speaker'] ?></b><span>Spiker</span>
    </a>
    <a class="tile <?= ($_GET['status'] ?? '') === 'new' ? 'tile--on' : '' ?>" href="?status=new">
      <b><?= $counts['new'] ?></b><span>Yangi</span>
    </a>
    <a class="tile <?= ($_GET['status'] ?? '') === 'confirmed' ? 'tile--on' : '' ?>" href="?status=confirmed">
      <b><?= $counts['confirmed'] ?></b><span>Tasdiqlangan</span>
    </a>
  </section>

  <form class="filters" method="get">
    <input type="search" name="q" value="<?= app_h($_GET['q'] ?? '') ?>" placeholder="Ism, tashkilot, email, telefon…">
    <select name="role">
      <option value="">Barcha turlar</option>
      <?php foreach ($ROLE_LABEL as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($_GET['role'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Barcha holatlar</option>
      <?php foreach ($STATUS_LABEL as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Qidirish</button>
    <a class="btn btn--quiet" href="?">Tozalash</a>
  </form>

  <p class="muted count"><?= count($rows) ?> ta yozuv</p>

  <?php if (!$rows): ?>
    <p class="empty">Hozircha hech kim roʻyxatdan oʻtmagan.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th><th>Sana</th><th>Tur</th><th>Ism</th><th>Tashkilot</th>
        <th>Aloqa</th><th>Yoʻnalish</th><th>Holat</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
      <tr class="row--<?= app_h($row['status'] ?? 'new') ?>">
        <td class="num"><?= (int) ($row['id'] ?? 0) ?></td>
        <td class="nowrap"><?= app_h(substr((string) ($row['created_at'] ?? ''), 0, 16)) ?></td>
        <td><span class="pill pill--<?= app_h($row['role'] ?? '') ?>"><?= app_h($ROLE_LABEL[$row['role'] ?? ''] ?? $row['role'] ?? '') ?></span></td>
        <td>
          <b><?= app_h($row['full_name'] ?? '') ?></b>
          <?php if (($row['position'] ?? '') !== ''): ?><br><span class="muted"><?= app_h($row['position']) ?></span><?php endif; ?>
        </td>
        <td>
          <?= app_h($row['organisation'] ?? '') ?>
          <?php if (($row['country'] ?? '') !== ''): ?><br><span class="muted"><?= app_h($row['country']) ?></span><?php endif; ?>
        </td>
        <td class="nowrap">
          <a href="mailto:<?= app_h($row['email'] ?? '') ?>"><?= app_h($row['email'] ?? '') ?></a><br>
          <a href="tel:<?= app_h(preg_replace('/[^\d+]/', '', (string) ($row['phone'] ?? '')) ?? '') ?>"><?= app_h($row['phone'] ?? '') ?></a>
        </td>
        <td><?= app_h($DIRECTION_LABEL[$row['direction'] ?? ''] ?? '') ?></td>
        <td>
          <form method="post" class="status">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
            <input type="hidden" name="back" value="<?= app_h($query) ?>">
            <select name="status" onchange="this.form.submit()">
              <?php foreach ($STATUS_LABEL as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($row['status'] ?? 'new') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
            <noscript><button type="submit">Saqlash</button></noscript>
          </form>
        </td>
      </tr>
      <?php if (($row['note'] ?? '') !== ''): ?>
      <tr class="note"><td></td><td colspan="7"><?= app_h($row['note']) ?></td></tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</main>
<?php endif; ?>

</body>
</html>
