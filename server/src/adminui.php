<?php
/**
 * The admin's shell: the same six doors, the same bar, the same locks.
 *
 * There are now five pages behind the password — the guest list, the site's
 * text, the project importer, the log and the accounts — and the three things
 * every one of them has to do first are identical: insist on HTTPS, send the
 * headers that keep a page of telephone numbers out of a cache, and refuse a
 * reader who may not be there.
 *
 * Doing that in five files is doing it four times too many: the day somebody
 * adds a sixth page is the day one of those three is forgotten, and the page
 * that forgets the third is a page anybody can open.
 *
 * So a page starts with one line:
 *
 *     $user = admin_boot($config, 'site.edit');
 *
 * and ends with admin_head()/admin_foot() around its own content.
 */

declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/audit.php';

/**
 * Every door the bar can offer, with the right each one needs.
 *
 * A reader is shown the doors they may walk through and no others: a menu of
 * links that answer 403 is a menu that teaches the reader to ignore the menu.
 */
const ADMIN_PAGES = [
    'regs' => ['href' => './', 'label' => 'Arizalar', 'cap' => 'registrations.view'],
    'site' => ['href' => 'site.php', 'label' => 'Saytni tahrirlash', 'cap' => 'site.edit'],
    'import' => ['href' => 'import.php', 'label' => 'Loyiha qoʻshish', 'cap' => 'projects.add'],
    'audit' => ['href' => 'audit.php', 'label' => 'Jurnal', 'cap' => 'audit.view'],
    'users' => ['href' => 'users.php', 'label' => 'Foydalanuvchilar', 'cap' => 'users.manage'],
];

/**
 * Start a page: headers, session, address check, and the right to be here.
 *
 * `$cap` is the capability the page needs. A reader who is not logged in is
 * sent to the login page; a reader who is logged in but may not open this one
 * is told so plainly rather than redirected in a circle.
 *
 * @return array<string,mixed> the account reading the page
 */
function admin_boot(array $config, string $cap): array
{
    guard_require_https($config);
    guard_headers('admin');
    guard_admin_session($config);

    if (!guard_ip_allowed($config, app_client_ip())) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Bu manzildan kirish yopilgan.\n";
        exit;
    }

    $user = users_current($config);
    if ($user === null) {
        header('Location: ./');
        exit;
    }
    users_touch($config, $user['id']);

    if (!user_can($user, $cap)) {
        http_response_code(403);
        admin_head($config, $user, 'Ruxsat yoʻq', '');
        echo '<main><p class="err big">Bu boʻlimga sizning huquqingiz yetmaydi — '
            . app_h(ROLE_LABELS[$user['role']] ?? $user['role'])
            . '. Kerak boʻlsa, adminkaning egasidan soʻrang.</p></main>';
        admin_foot();
        exit;
    }
    return $user;
}

/* ------------------------------------------------------------------ csrf */

/**
 * The token every state-changing form carries.
 *
 * One per session, not one per form: the admin is a handful of small forms
 * on the same page and a token per form would be a session that grows with
 * every page view.
 */
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

/**
 * The token, for a request that carries it in a header rather than a field.
 *
 * The upload posts a file body, not a form, so there is no field to put it
 * in. Same token, same comparison.
 */
function admin_check_token_header(): bool
{
    $given = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    return isset($_SESSION['csrf'])
        && is_string($given)
        && hash_equals((string) $_SESSION['csrf'], $given);
}

/* ------------------------------------------------------------------- csv */

/**
 * One cell, written so that a spreadsheet reads it as what was typed.
 *
 * Excel and LibreOffice read a cell beginning with `=`, `+`, `-` or `@` as a
 * formula, and both exports here — the guest list and this log — are files
 * somebody opens on their own machine with other people's telephone numbers
 * in the next column. A registration form is public: a formula typed into the
 * name field is a live link in the organiser's spreadsheet. Nothing refuses
 * it on the way in, because none of those characters is wrong in a name — it
 * is the spreadsheet, not the data, that has to be told.
 *
 * It also repairs the telephone column: every number here is written +998…,
 * which Excel otherwise evaluates as a sum.
 */
function admin_csv_cell(string $value): string
{
    return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value;
}

/** The header every CSV this admin writes begins with. */
function admin_csv_start(string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
}

/* ---------------------------------------------------------------- layout */

/**
 * The top of every page: the bar, the doors, and who is reading.
 *
 * `$active` is the key in ADMIN_PAGES this page belongs to, which is the one
 * that is filled in. An empty string means none of them.
 */
function admin_head(array $config, ?array $user, string $title, string $active): void
{
    $siteName = (string) ($config['site_name'] ?? 'Forum');
    ?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= app_h($title) ?> — <?= app_h($siteName) ?></title>
<link rel="stylesheet" href="admin.css">
<script src="admin.js" defer></script>
</head>
<body>
<header class="bar">
  <div class="bar__title">
    <div>
      <strong><?= app_h($title) ?></strong>
      <span class="muted"><?= app_h($siteName) ?></span>
    </div>
  </div>
  <?php if ($user !== null): ?>
  <nav class="bar__nav" aria-label="Boʻlimlar">
    <?php foreach (ADMIN_PAGES as $key => $page): ?>
      <?php if (user_can($user, $page['cap'])): ?>
        <a class="tab<?= $key === $active ? ' tab--on' : '' ?>" href="<?= app_h($page['href']) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= app_h($page['label']) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="bar__right">
    <span class="who" title="<?= app_h(ROLE_LABELS[$user['role']] ?? $user['role']) ?>">
      <b><?= app_h($user['name']) ?></b>
      <i class="chip chip--role chip--<?= app_h($user['role']) ?>"><?= app_h(admin_role_word($user['role'])) ?></i>
    </span>
    <a class="btn btn--plain" href="./?action=logout">Chiqish</a>
  </div>
  <?php endif; ?>
</header>
    <?php
}

/** The one-word form of a role, for the chip beside a name. */
function admin_role_word(string $role): string
{
    return ['owner' => 'egasi', 'editor' => 'muharrir', 'viewer' => 'kuzatuvchi'][$role] ?? $role;
}

function admin_foot(): void
{
    echo "\n</body>\n</html>\n";
}

/**
 * A message at the top of a page, kept across the redirect that follows a
 * save.
 *
 * Everything here posts and redirects — a save that leaves a POST in the
 * history is a save the back button repeats — so the sentence describing
 * what happened has to survive one redirect and then disappear.
 */
function admin_flash(string $kind, string $text): void
{
    $_SESSION['flash'] = ['kind' => $kind, 'text' => $text];
}

function admin_flash_take(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function admin_flash_print(): void
{
    $flash = admin_flash_take();
    if ($flash === null) {
        return;
    }
    $class = $flash['kind'] === 'err' ? 'err' : 'ok';
    echo '<p class="' . $class . ' big">' . app_h((string) $flash['text']) . '</p>';
}

/**
 * The query string to come back to after a change.
 *
 * It is a reader's own filter — `?q=ivanov&status=new` — carried through a
 * redirect so that confirming one registration does not throw away the search
 * that found it. It is also a value from the request going into a `Location:`
 * header, so it is taken as what it is: the characters a query string is made
 * of, and nothing else.
 */
function admin_back(): string
{
    $back = $_POST['back'] ?? '';
    if (!is_string($back)) {
        return '';
    }
    return (string) preg_replace('/[^A-Za-z0-9_=&%.\-+]/', '', $back);
}
