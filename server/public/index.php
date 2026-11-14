<?php
/**
 * The admin's front door and its first room: log in, read the registrations,
 * change a status, hand over a badge, export a CSV.
 *
 * Server-rendered on purpose. It is a table behind a password on somebody
 * else's server; a build step and a bundle would be two more things that can
 * be out of date when a colleague opens it to check who has registered.
 *
 * The other rooms are site.php (what the site says), import.php (adding
 * projects), audit.php (what everyone did) and users.php (who may do it).
 * What they share is in ../src/adminui.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/adminui.php';
require_once __DIR__ . '/../src/ticket.php';
require_once __DIR__ . '/../src/captcha.php';

$config = app_config();

/*
 * The headers first, the session second, the page last.
 *
 * guard_headers() has to run before anything is printed, and the session has
 * to be started before anything reads it — including the login throttle,
 * which is the first thing a request to this page touches. The login page
 * widens the content policy by exactly as much as Cloudflare's check needs,
 * and not at all when that check is not configured.
 */
guard_require_https($config);
guard_headers('admin', captcha_turnstile($config) ? [
    'script' => ['https://challenges.cloudflare.com'],
    'frame' => ['https://challenges.cloudflare.com'],
    'connect' => ['https://challenges.cloudflare.com'],
] : []);
guard_admin_session($config);

$ip = app_client_ip();
if (!guard_ip_allowed($config, $ip)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bu manzildan kirish yopilgan.\n";
    exit;
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$error = '';
$wait = 0;

/* ------------------------------------------------------------------ auth */

if ($action === 'logout') {
    if (!empty($_SESSION['admin'])) {
        audit_log($config, 'logout');
    }
    $_SESSION = [];
    session_destroy();
    header('Location: ?');
    exit;
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    /*
     * The order matters and is the whole of the lock.
     *
     *   1. is the address already locked out?
     *   2. is there a person here? — answered before the password is read,
     *      and a wrong answer costs nothing but a new question, because the
     *      lock below is a single strike and misreading five drawn letters
     *      must not spend it;
     *   3. is the password right? — one wrong answer locks the address.
     */
    $login = trim((string) ($_POST['login'] ?? ''));
    $given = (string) ($_POST['password'] ?? '');
    $wait = guard_login_wait($config, $ip);

    if ($wait > 0) {
        // Locked out, and not told whether the password was right: that is
        // the one thing a word list would still learn from a locked door.
        $error = 'locked';
        audit_log($config, 'login.locked', ['login' => $login]);
    } else {
        [$human, $why] = captcha_verify($config, $_POST, $ip);
        if (!$human) {
            $error = $why === 'unreachable' ? 'captcha-down' : 'captcha';
            audit_log($config, 'login.captcha', ['login' => $login, 'sabab' => $why]);
        } else {
            /* An empty login is the installation that has always had one
               password and one person: that account is called admin. */
            $account = users_find($config, $login === '' ? 'admin' : $login);
            $hash = $account === null ? '' : (string) $account['hash'];
            if ($hash === '' && users_find($config, 'admin') === null) {
                $error = 'no-hash';
            } elseif ($account !== null && $account['active'] && $hash !== '' && password_verify($given, $hash)) {
                guard_login_ok($config, $ip);
                guard_admin_login($config, $account);
                users_touch($config, (string) $account['id']);
                audit_log($config, 'login', ['login' => (string) $account['login']]);
                header('Location: ?');
                exit;
            } else {
                /*
                 * Costs the same whether the password was close, the account
                 * is switched off or there is no such login at all. A door
                 * that answers faster for a real name is a door that lists
                 * its own staff.
                 */
                if ($hash === '') {
                    password_verify($given, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
                }
                usleep(400000);
                guard_login_failed($config, $ip);
                $wait = guard_login_wait($config, $ip);
                $error = $wait > 0 ? 'locked' : 'bad';
                audit_log($config, 'login.failed', ['login' => $login, 'bloklandi' => $wait > 0]);
            }
        }
    }
}

$user = users_current($config);
$authed = $user !== null;
if ($authed) {
    users_touch($config, (string) $user['id']);
}

/* Why the last session ended, if it ended by itself. Read once. */
$ended = (string) ($_SESSION['ended'] ?? '');
unset($_SESSION['ended']);

/* --------------------------------------------------------------- actions */

/**
 * Changing one's own password.
 *
 * The current one is asked for as well, because the person at the keyboard is
 * not necessarily the person who logged in — an unlocked laptop should not be
 * a new password. Changing it ends every other session that account has open.
 *
 * Where the new hash goes depends on whether this installation has accounts
 * yet: the one-password installation keeps writing data/admin.json, exactly
 * as before, so nothing about it changes until somebody adds a second person.
 */
$passwordSaved = false;
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'password') {
    $new = (string) ($_POST['new'] ?? '');
    $complaint = guard_password_complaint($new);
    if (!admin_check_token()) {
        $error = 'csrf';
    } elseif (!password_verify((string) ($_POST['current'] ?? ''), (string) $user['hash'])) {
        usleep(400000);
        $error = 'pw-current';
    } elseif ($new !== (string) ($_POST['again'] ?? '')) {
        $error = 'pw-again';
    } elseif ($complaint !== '') {
        $error = 'pw-' . $complaint;
    } else {
        try {
            if ($user['legacy']) {
                guard_password_set($config, $new);
                $_SESSION['pwv'] = guard_password_version($config);
            } else {
                users_set_password($config, (string) $user['id'], $new);
                $fresh = users_by_id($config, (string) $user['id']);
                $_SESSION['upwv'] = (int) ($fresh['pwv'] ?? 0);
            }
            $user = users_current($config);
            audit_log($config, 'password.changed', ['login' => (string) $user['login']]);
            $passwordSaved = true;
        } catch (Throwable $e) {
            error_log('[forum-admin] password: ' . $e->getMessage());
            $error = 'pw-write';
        }
    }
}

if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'status') {
    if (admin_check_token() && user_can($user, 'registrations.edit')) {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (storage_set_status($config, $id, $status)) {
            audit_log($config, 'registration.status', ['holat' => $status], '#' . $id);
        }
    }
    header('Location: ?' . admin_back());
    exit;
}

/**
 * Handing a VIP's or a speaker's badge over, or taking it back.
 *
 * The two roles that are invited rather than registered have no badge until
 * somebody here decides they do — see TICKET_AUTO in src/app.php.
 */
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'ticket') {
    if (admin_check_token() && user_can($user, 'registrations.edit')) {
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $on = ($_POST['on'] ?? '') === '1';
            ticket_release($config, $id, $on);
            audit_log($config, 'registration.ticket', ['berildi' => $on], '#' . $id);
        } catch (Throwable $e) {
            error_log('[forum-admin] ticket: ' . $e->getMessage());
        }
    }
    header('Location: ?' . admin_back());
    exit;
}

if ($authed && $action === 'csv') {
    if (!user_can($user, 'registrations.export')) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Arizalar roʻyxatini faqat adminkaning egasi yuklab oladi.\n";
        exit;
    }
    $rows = admin_filter(storage_all($config), $_GET);
    audit_log($config, 'registration.export', ['qatorlar' => count($rows)]);
    admin_csv_start('registrations-' . gmdate('Y-m-d') . '.csv');
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
            $line[] = admin_csv_cell((string) ($row[$f] ?? ''));
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
        if ($role !== '' && app_role($row['role'] ?? '') !== $role) {
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

$counts = ['all' => count($all), 'vip' => 0, 'investor' => 0, 'visitor' => 0, 'speaker' => 0,
    'new' => 0, 'confirmed' => 0, 'declined' => 0];
foreach ($all as $row) {
    $r = app_role($row['role'] ?? '');
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

$ROLE_LABEL = ['vip' => 'VIP', 'investor' => 'Investor', 'visitor' => 'Mehmon', 'speaker' => 'Spiker'];
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
$mayEdit = user_can($user, 'registrations.edit');

if (!$authed):
    $kind = captcha_kind($config);
    $challenge = $kind === 'turnstile' ? null : captcha_challenge($config);
?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Adminka — <?= app_h($config['site_name'] ?? 'Forum') ?></title>
<link rel="stylesheet" href="admin.css">
<script src="admin.js" defer></script>
<?php if ($kind === 'turnstile'): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></script>
<?php endif; ?>
</head>
<body class="is-gate">
<main class="gate">
  <form method="post" class="card">
    <h1>Adminka</h1>
    <p class="muted"><?= app_h($config['site_name'] ?? '') ?></p>
    <input type="hidden" name="action" value="login">
    <label for="login">Login</label>
    <input id="login" name="login" type="text" autocomplete="username" autocapitalize="none"
           spellcheck="false" placeholder="admin" value="<?= app_h($_POST['login'] ?? '') ?>">
    <label for="pw">Parol</label>
    <input id="pw" name="password" type="password" autocomplete="current-password" required autofocus>

    <?php if ($kind === 'turnstile'): ?>
      <div class="cf-turnstile" data-sitekey="<?= app_h((string) $config['turnstile_site_key']) ?>" data-theme="light"></div>
    <?php elseif ($kind === 'image'): ?>
      <label for="cap">Rasmdagi belgilarni kiriting</label>
      <div class="captcha">
        <img src="captcha.php" alt="Tekshiruv rasmi" width="190" height="64" id="capimg">
        <button type="button" class="btn btn--quiet" data-captcha-reload>Boshqa rasm</button>
      </div>
      <input id="cap" name="captcha" type="text" inputmode="latin" autocomplete="off"
             autocapitalize="characters" spellcheck="false" required maxlength="8">
    <?php else: ?>
      <label for="cap">Tekshiruv: <?= app_h((string) $challenge['words']) ?> nechchi?</label>
      <input id="cap" name="captcha" type="text" inputmode="numeric" autocomplete="off" required maxlength="4">
    <?php endif; ?>

    <?php if ($error === 'bad'): ?>
      <p class="err">Login yoki parol notoʻgʻri.</p>
    <?php elseif ($error === 'captcha'): ?>
      <p class="err">Tekshiruv javobi notoʻgʻri — qaytadan urinib koʻring. Parol tekshirilmadi.</p>
    <?php elseif ($error === 'captcha-down'): ?>
      <p class="err">
        Tekshiruv xizmatiga (Cloudflare) ulanib boʻlmadi — serverda tashqi tarmoq yoʻqqa oʻxshaydi.
        <code>server/.env</code> dagi <code>TURNSTILE_SITE_KEY</code> ni boʻshatsangiz,
        adminka oʻzining ichki tekshiruviga qaytadi.
      </p>
    <?php elseif ($error === 'locked'): ?>
      <p class="err">
        Notoʻgʻri parol kiritildi — bu manzil <?= (int) ceil($wait / 60) ?> daqiqaga yopildi.
        Shoshilinch boʻlsa, serverda <code>php server/bin/unlock.php</code> buyrugʻini bering.
      </p>
    <?php elseif ($error === 'no-hash'): ?>
      <p class="err">
        Parol hali oʻrnatilmagan. Serverda <code>server/.env</code> faylidagi
        <code>ADMIN_PASSWORD_HASH</code> ni toʻldiring:
        <code>php server/bin/password.php</code>
      </p>
    <?php elseif ($ended === 'timeout'): ?>
      <p class="muted">Sessiya vaqti tugadi — qaytadan kiring.</p>
    <?php elseif ($ended === 'password'): ?>
      <p class="muted">Parol oʻzgartirildi — yangi parol bilan kiring.</p>
    <?php elseif ($ended === 'access'): ?>
      <p class="muted">Huquqingiz yoki parolingiz oʻzgardi — qaytadan kiring.</p>
    <?php endif; ?>
    <button type="submit">Kirish</button>
    <p class="muted small gate__note">
      Bitta notoʻgʻri parol — manzil vaqtincha yopiladi. Loginni bilmasangiz
      boʻsh qoldiring.
    </p>
  </form>
</main>
</body>
</html>
<?php
    exit;
endif;

admin_head($config, $user, 'Arizalar', 'regs');
?>
<div class="subbar">
  <span class="muted store"><?= app_h(storage_describe()) ?></span>
  <span class="subbar__spacer"></span>
  <?php if (user_can($user, 'registrations.export')): ?>
    <a class="btn btn--lead" href="?action=csv&amp;<?= app_h($query) ?>">CSV yuklab olish</a>
  <?php endif; ?>
  <a class="btn" href="?action=password">Parolim</a>
</div>

<main>
  <?php admin_flash_print(); ?>
  <?php if ($fatal !== ''): ?>
    <p class="err big">Maʼlumotlarni oʻqib boʻlmadi: <?= app_h($fatal) ?></p>
  <?php endif; ?>

  <?php if ($passwordSaved): ?>
    <p class="ok big">Parol oʻzgartirildi. Boshqa brauzerlardagi sessiyalar yopildi.</p>
  <?php endif; ?>

  <?php if ($action === 'password' || str_starts_with($error, 'pw-') || $error === 'csrf'): ?>
  <section class="pw">
    <form method="post" class="card">
      <h2>Parolni oʻzgartirish</h2>
      <input type="hidden" name="action" value="password">
      <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
      <label for="cur">Hozirgi parol</label>
      <input id="cur" name="current" type="password" autocomplete="current-password" required>
      <label for="new">Yangi parol</label>
      <input id="new" name="new" type="password" autocomplete="new-password" required minlength="10">
      <label for="again">Yangi parol (yana bir marta)</label>
      <input id="again" name="again" type="password" autocomplete="new-password" required minlength="10">
      <?php if ($error === 'pw-current'): ?>
        <p class="err">Hozirgi parol notoʻgʻri.</p>
      <?php elseif ($error === 'pw-again'): ?>
        <p class="err">Ikki marta kiritilgan yangi parol bir xil emas.</p>
      <?php elseif ($error === 'pw-short'): ?>
        <p class="err">Parol kamida 10 belgidan boʻlsin.</p>
      <?php elseif ($error === 'pw-digits'): ?>
        <p class="err">Faqat raqamdan iborat parol boʻlmaydi.</p>
      <?php elseif ($error === 'pw-write'): ?>
        <p class="err">
          Saqlab boʻlmadi: <code>server/data/</code> papkasiga yozish huquqi yoʻq.
        </p>
      <?php elseif ($error === 'csrf'): ?>
        <p class="err">Sahifa eskirgan — qaytadan urinib koʻring.</p>
      <?php endif; ?>
      <p class="muted">
        Kamida 10 belgi. Saqlaganingizdan keyin boshqa qurilmalardagi
        sessiyalar yopiladi, bu brauzer esa ochiq qoladi.
      </p>
      <button type="submit">Saqlash</button>
      <a class="btn btn--quiet" href="?">Bekor qilish</a>
    </form>
  </section>
  <?php endif; ?>

  <?php
  /*
   * The counters, which are three groups and not one row of seven: how many
   * there are, who they are, and where each of them stands. `tile--group`
   * marks the first of a group, which is where the strip is cut.
   */
  ?>
  <section class="tiles">
    <a class="tile <?= ($_GET['role'] ?? '') === '' && ($_GET['status'] ?? '') === '' ? 'tile--on' : '' ?>" href="?">
      <b><?= $counts['all'] ?></b><span>Jami</span>
    </a>
    <?php $first = true; foreach ($ROLE_LABEL as $k => $v): ?>
      <a class="tile <?= $first ? 'tile--group ' : '' ?><?= ($_GET['role'] ?? '') === $k ? 'tile--on' : '' ?>" href="?role=<?= $k ?>">
        <b><?= $counts[$k] ?></b><span><?= $v ?></span>
      </a>
    <?php $first = false; endforeach; ?>
    <a class="tile tile--group <?= ($_GET['status'] ?? '') === 'new' ? 'tile--on' : '' ?>" href="?status=new">
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
  <?php
  /*
   * `data-th` on every cell is what lets the stylesheet turn each row into a
   * card on a telephone: the cell carries its own column heading, so the
   * table head can be hidden and nothing loses its name. The strings are the
   * same ones in the `<th>` row above, written once here rather than
   * repeated in CSS, where they could drift out of step with the headings.
   */
  ?>
  <table class="regs">
    <thead>
      <tr>
        <th>#</th><th>Sana</th><th>Tur</th><th>Ism</th><th>Tashkilot</th>
        <th>Aloqa</th><th>Yoʻnalish</th><th>Holat</th><th>Bilet</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
      <tr class="row--<?= app_h($row['status'] ?? 'new') ?>">
        <td class="num cell--id" data-th="#"><?= (int) ($row['id'] ?? 0) ?></td>
        <td class="nowrap cell--when" data-th="Sana"><?= app_h(substr((string) ($row['created_at'] ?? ''), 0, 16)) ?></td>
        <td data-th="Tur"><span class="pill pill--<?= app_h($row['role'] ?? '') ?>"><?= app_h($ROLE_LABEL[$row['role'] ?? ''] ?? $row['role'] ?? '') ?></span></td>
        <td class="cell--name" data-th="Ism">
          <b><?= app_h($row['full_name'] ?? '') ?></b>
          <?php if (($row['position'] ?? '') !== ''): ?><br><span class="muted"><?= app_h($row['position']) ?></span><?php endif; ?>
        </td>
        <td data-th="Tashkilot">
          <?= app_h($row['organisation'] ?? '') ?>
          <?php if (($row['country'] ?? '') !== ''): ?><br><span class="muted"><?= app_h($row['country']) ?></span><?php endif; ?>
        </td>
        <td class="nowrap" data-th="Aloqa">
          <a href="mailto:<?= app_h($row['email'] ?? '') ?>"><?= app_h($row['email'] ?? '') ?></a><br>
          <a href="tel:<?= app_h(preg_replace('/[^\d+]/', '', (string) ($row['phone'] ?? '')) ?? '') ?>"><?= app_h($row['phone'] ?? '') ?></a>
        </td>
        <td data-th="Yoʻnalish"><?= app_h($DIRECTION_LABEL[$row['direction'] ?? ''] ?? '') ?></td>
        <td data-th="Holat">
          <?php if ($mayEdit): ?>
          <form method="post" class="status">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
            <input type="hidden" name="back" value="<?= app_h($query) ?>">
            <select name="status" data-autosubmit>
              <?php foreach ($STATUS_LABEL as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($row['status'] ?? 'new') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
            <noscript><button type="submit">Saqlash</button></noscript>
          </form>
          <?php else: ?>
            <?= app_h($STATUS_LABEL[$row['status'] ?? 'new'] ?? '') ?>
          <?php endif; ?>
        </td>
        <?php
        /**
         * An investor and a visitor already have this link — it came back
         * with their registration, and the button is here for the times
         * they lost it. A VIP and a speaker have no badge until it is
         * handed over here.
         */
        $auto = in_array(app_role($row['role'] ?? ''), TICKET_AUTO, true);
        $given = $auto || ticket_released($config, (int) $row['id']);
        ?>
        <td class="nowrap ticket" data-th="Bilet">
          <?php if ($given): ?>
            <a class="btn btn--quiet" href="/ticket/<?= app_h(ticket_number((int) $row['id'], $config)) ?>" target="_blank" rel="noopener">E-bilet</a>
          <?php endif; ?>
          <?php if (!$auto && $mayEdit): ?>
            <form method="post" class="ticket__give">
              <input type="hidden" name="action" value="ticket">
              <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
              <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
              <input type="hidden" name="back" value="<?= app_h($query) ?>">
              <input type="hidden" name="on" value="<?= $given ? '0' : '1' ?>">
              <button type="submit" class="btn btn--quiet"><?= $given ? 'Bekor qilish' : 'Biletni berish' ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php if (($row['note'] ?? '') !== ''): ?>
      <tr class="note row--<?= app_h($row['status'] ?? 'new') ?>"><td></td><td colspan="8"><span><?= app_h($row['note']) ?></span></td></tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</main>
<?php
admin_foot();
