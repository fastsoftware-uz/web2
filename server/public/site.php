<?php
/**
 * The admin's second half: editing what the site says.
 *
 * index.php is the registrations. This is everything else — the event's own
 * details, the eight chapters of text, the programme, the contacts, and the
 * project portfolio. See ../src/site.php for how an edit reaches the live
 * page and why it is stored as an override rather than written into the file
 * the build produces.
 *
 * Server-rendered and form-posted, for the same reason index.php is: this
 * runs on somebody else's server and a build step is one more thing that can
 * be out of date when a colleague opens it to fix a date.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/app.php';
require_once __DIR__ . '/../src/site.php';
require_once __DIR__ . '/../src/guard.php';

$config = app_config();

/* The same headers, the same session and the same locks as the list; this
   page shows no telephone numbers but it can change what the site says. */
guard_require_https($config);
guard_headers('admin');
guard_admin_session($config);

if (!guard_ip_allowed($config, app_client_ip())) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bu manzildan kirish yopilgan.\n";
    exit;
}

if (empty($_SESSION['admin'])) {
    header('Location: ./');
    exit;
}

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

/* ------------------------------------------------------------------ names */

/**
 * Uzbek names for the sections and the fields inside them.
 *
 * The keys are an English build output's and a colleague opening this to fix
 * a date should not have to read them. Anything not named here falls back to
 * its own key, which is the same rule the site's own field dictionary
 * follows: a field nobody named is still shown, in the words it has.
 */
const SECTION_LABEL = [
    'meta' => 'Sozlamalar',
    'event' => 'Tadbir: sana, shahar, joy',
    'intro' => 'Kirish',
    'goals' => 'Maqsadlar',
    'themes' => 'Yoʻnalishlar',
    'participants' => 'Ishtirokchilar',
    'sessions' => 'Sessiyalar',
    'programme' => 'Dastur',
    'standard' => 'Standart',
    'criteria' => 'Mezonlar',
    'mechanism' => 'Mexanizm',
    'stages' => 'Bosqichlar',
    'outcomes' => 'Natijalar',
    'promotion' => 'Targʻibot',
    'contact' => 'Aloqa',
    'sources' => 'Manbalar',
];

const FIELD_LABEL = [
    'name' => 'Nomi', 'shortName' => 'Qisqa nomi', 'edition' => 'Tartibi',
    'dateStart' => 'Boshlanish sanasi', 'dateEnd' => 'Tugash sanasi',
    'city' => 'Shahar', 'country' => 'Mamlakat', 'within' => 'Doirasida',
    'venue' => 'Joyi', 'organiser' => 'Tashkilotchi', 'organisation' => 'Tashkilot',
    'email' => 'Email', 'phone' => 'Telefon', 'phoneHref' => 'Telefon havolasi',
    'shortPhone' => 'Qisqa raqam', 'postcode' => 'Pochta indeksi', 'address' => 'Manzil',
    'lat' => 'Kenglik', 'lon' => 'Uzunlik', 'title' => 'Sarlavha', 'body' => 'Matn',
    'subtitle' => 'Izoh', 'purpose' => 'Maqsadi', 'paragraphs' => 'Paragraflar',
    'note' => 'Eslatma', 'items' => 'Bandlar', 'topics' => 'Mavzular',
    'who' => 'Kim', 'category' => 'Toifa', 'icon' => 'Belgi', 'n' => 'Raqam',
    'roman' => 'Rim raqami', 'block' => 'Blok', 'number' => 'Raqam',
    'workingTitle' => 'Ishchi nomi', 'source' => 'Manba', 'publisher' => 'Nashr etuvchi',
    'document' => 'Hujjat', 'asOf' => 'Holat sanasi', 'id' => 'Kalit',
    'titleI18n' => 'Sarlavha', 'subtitleI18n' => 'Izoh', 'overviewI18n' => 'Tavsif',
    'overview' => 'Tavsif (inglizcha)', 'investment' => 'Investitsiya (raqam)',
    'currency' => 'Valyuta', 'investmentDisplay' => 'Investitsiya (yozuv)',
    'region' => 'Hudud', 'segment' => 'Yoʻnalish', 'metrics' => 'Koʻrsatkichlar',
    'concept' => 'Konsepsiya', 'opportunity' => 'Imkoniyat', 'whyInvest' => 'Nega investitsiya',
    'place' => 'Joylashuv', 'statusNote' => 'Holat izohi', 'label' => 'Nomi',
    'value' => 'Qiymati', 'sheets' => 'Manba varaqlari', 'photos' => 'Rasmlar',
    'issues' => 'Izohlar', 'featured' => 'Birinchi oʻringa',
    'uz' => 'Oʻzbekcha', 'ru' => 'Ruscha', 'en' => 'Inglizcha',
];

const SEGMENT_LABEL = [
    'infrastructure' => 'Mehmonxona va infratuzilma',
    'heritage' => 'Madaniy meros',
    'eco' => 'Ekologik va ekstremal',
    'wellness' => 'Tibbiy va wellness',
    'leisure' => 'Koʻngilochar va oilaviy',
    'transport' => 'Transport',
];

function label_for(string $key): string
{
    return FIELD_LABEL[$key] ?? SECTION_LABEL[$key] ?? $key;
}

/* ----------------------------------------------------------------- state */

$key = isset($_GET['f']) && isset(SITE_FILES[$_GET['f']]) ? (string) $_GET['f'] : 'forum';
$page = (string) ($_GET['page'] ?? 'sections');
$notice = '';
$problem = '';

$baseNote = null;
try {
    $base = site_base($config, $key, $baseNote);
} catch (Throwable $e) {
    $base = null;
    $problem = $e->getMessage();
}
$overrides = site_overrides($config, $key);

/**
 * A deploy has landed since the last save, so re-apply on top of it.
 *
 * This is the only moment the backend can notice: nothing tells it when the
 * site is replaced, so it compares what is published against the hash it
 * wrote last and finds out when somebody next opens the admin. Publishing
 * from a GET is not pretty; leaving the organiser's corrections silently
 * undone by a deploy would be worse.
 */
if (
    $baseNote === 'redeployed'
    && $base !== null
    && ($overrides['values'] !== [] || $overrides['hidden'] !== [] || $overrides['added'] !== [])
) {
    try {
        site_publish($config, $key);
        $overrides = site_overrides($config, $key);
    } catch (Throwable $e) {
        $problem = $e->getMessage();
    }
}

/* --------------------------------------------------------------- actions */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $base !== null) {
    if (!admin_check_token()) {
        $problem = 'Sahifa eskirgan — qaytadan urinib koʻring.';
    } else {
        $do = (string) ($_POST['do'] ?? '');
        try {
            if ($do === 'save') {
                $fields = is_array($_POST['f'] ?? null) ? $_POST['f'] : [];
                $changed = 0;
                $cleared = 0;
                foreach ($fields as $path => $posted) {
                    $path = (string) $path;
                    if (!site_has($base, $path)) {
                        continue;
                    }
                    $original = site_get($base, $path);
                    $value = site_cast($original, is_string($posted) ? $posted : '');
                    if (site_same($value, $original)) {
                        if (isset($overrides['values'][$path])) {
                            unset($overrides['values'][$path]);
                            $cleared++;
                        }
                        continue;
                    }
                    if (($overrides['values'][$path] ?? null) !== $value) {
                        $overrides['values'][$path] = $value;
                        $changed++;
                    }
                }
                // A checkbox that is off is not posted at all, so every
                // boolean on the form carries a hidden companion naming it.
                foreach ((array) ($_POST['bools'] ?? []) as $path) {
                    $path = (string) $path;
                    if (isset($fields[$path]) || !site_has($base, $path)) {
                        continue;
                    }
                    $original = site_get($base, $path);
                    if ($original === true) {
                        $overrides['values'][$path] = false;
                        $changed++;
                    } elseif (isset($overrides['values'][$path])) {
                        unset($overrides['values'][$path]);
                        $cleared++;
                    }
                }
                site_save_overrides($config, $key, $overrides);
                $result = site_publish($config, $key);
                $notice = $changed || $cleared
                    ? "Saqlandi: {$changed} ta oʻzgarish, {$cleared} tasi asl holiga qaytdi. Sayt yangilandi."
                    : 'Oʻzgarish yoʻq edi — sayt baribir qayta nashr etildi.';
                $overrides = site_overrides($config, $key);
            } elseif ($do === 'hide' || $do === 'show') {
                $id = (string) ($_POST['id'] ?? '');
                $overrides['hidden'] = array_values(array_diff($overrides['hidden'], [$id]));
                if ($do === 'hide' && $id !== '') {
                    $overrides['hidden'][] = $id;
                }
                site_save_overrides($config, $key, $overrides);
                site_publish($config, $key);
                $notice = $do === 'hide' ? 'Loyiha saytdan olib qoʻyildi.' : 'Loyiha saytga qaytarildi.';
                $overrides = site_overrides($config, $key);
            } elseif ($do === 'revert') {
                $path = (string) ($_POST['path'] ?? '');
                unset($overrides['values'][$path]);
                site_save_overrides($config, $key, $overrides);
                site_publish($config, $key);
                $notice = 'Maydon asl holiga qaytarildi.';
                $overrides = site_overrides($config, $key);
            } elseif ($do === 'revert-all') {
                $overrides['values'] = [];
                $overrides['hidden'] = [];
                site_save_overrides($config, $key, $overrides);
                site_publish($config, $key);
                $notice = 'Barcha oʻzgarishlar bekor qilindi — sayt asl holiga qaytdi.';
                $overrides = site_overrides($config, $key);
            } elseif ($do === 'republish') {
                $result = site_publish($config, $key);
                $notice = 'Sayt qayta nashr etildi: ' . $result['applied'] . ' ta oʻzgarish qoʻllandi.';
                $overrides = site_overrides($config, $key);
            }
        } catch (Throwable $e) {
            $problem = $e->getMessage();
        }
        // Re-read the base: publishing may have re-taken it.
        $base = site_base($config, $key, $baseNote);
    }
}

$stale = [];
$merged = $base === null ? null : site_merged($base, $overrides, $key, $stale);

/* ------------------------------------------------------------- rendering */

/**
 * One editable leaf.
 *
 * `$path` is what the override will be keyed by; `$value` is what the base
 * says, and `$live` what it says after the overrides, which is what goes in
 * the box.
 */
function field(string $path, string $name, mixed $base, mixed $live, bool $edited): void
{
    $kind = site_kind($base);
    $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $path);
    echo '<div class="fld' . ($edited ? ' fld--edited' : '') . '">';
    echo '<label for="' . app_h($id) . '">' . app_h($name);
    if ($edited) {
        echo ' <span class="chip">oʻzgartirilgan</span>';
    }
    echo '</label>';

    if ($kind === 'bool') {
        echo '<input type="hidden" name="bools[]" value="' . app_h($path) . '">';
        echo '<label class="switch"><input id="' . app_h($id) . '" type="checkbox" name="f[' . app_h($path) . ']" value="1"'
            . ($live === true ? ' checked' : '') . '> <span>Yoqilgan</span></label>';
    } elseif ($kind === 'date') {
        echo '<input id="' . app_h($id) . '" type="date" name="f[' . app_h($path) . ']" value="'
            . app_h((string) $live) . '">';
    } elseif ($kind === 'text') {
        echo '<textarea id="' . app_h($id) . '" name="f[' . app_h($path) . ']" rows="4">'
            . app_h((string) $live) . '</textarea>';
    } elseif ($kind === 'lines') {
        echo '<textarea id="' . app_h($id) . '" name="f[' . app_h($path) . ']" rows="4" '
            . 'placeholder="Har bir qator — alohida band">'
            . app_h(implode("\n", array_map('strval', (array) $live))) . '</textarea>';
        echo '<small>Har bir qator — alohida band.</small>';
    } else {
        echo '<input id="' . app_h($id) . '" type="text" name="f[' . app_h($path) . ']" value="'
            . app_h($live === null ? '' : (string) $live) . '">';
    }
    echo '</div>';
}

/** A node: either three language boxes, a group, or a leaf. */
function node(string $path, string $name, mixed $base, mixed $live, array $overrides, int $depth = 0): void
{
    if (site_kind($base) !== 'nested') {
        field($path, $name, $base, $live, isset($overrides['values'][$path]));
        return;
    }
    if (!is_array($base)) {
        return;
    }

    $keys = array_keys($base);
    $isI18n = !site_is_list($base) && $keys === ['uz', 'ru', 'en'];

    if ($isI18n) {
        echo '<fieldset class="i18n"><legend>' . app_h($name) . '</legend>';
        foreach (['uz' => 'Oʻzbekcha', 'ru' => 'Ruscha', 'en' => 'Inglizcha'] as $lang => $title) {
            node("$path.$lang", $title, $base[$lang], $live[$lang] ?? null, $overrides, $depth + 1);
        }
        echo '</fieldset>';
        return;
    }

    echo '<fieldset class="group"><legend>' . app_h($name) . '</legend>';
    foreach ($base as $k => $v) {
        $segment = site_is_list($base) && is_array($v) && isset($v['id'])
            ? (string) $v['id']
            : (string) $k;
        $childName = site_is_list($base)
            ? trim(((string) ((int) $k + 1)) . '. ' . short_title($v))
            : label_for((string) $k);
        node(
            $path === '' ? $segment : "$path.$segment",
            $childName,
            $v,
            is_array($live) ? ($live[$k] ?? null) : null,
            $overrides,
            $depth + 1,
        );
    }
    echo '</fieldset>';
}

/** A one-line name for an item in a list, so the form is navigable. */
function short_title(mixed $item): string
{
    if (is_string($item)) {
        return mb_substr_safe($item, 60);
    }
    if (!is_array($item)) {
        return '';
    }
    foreach (['title', 'label', 'name', 'block', 'category', 'who'] as $k) {
        $v = $item[$k] ?? null;
        if (is_string($v) && $v !== '') {
            return mb_substr_safe($v, 60);
        }
        if (is_array($v) && isset($v['uz'])) {
            return mb_substr_safe((string) $v['uz'], 60);
        }
    }
    return (string) ($item['id'] ?? '');
}

function mb_substr_safe(string $s, int $n): string
{
    $cut = function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
    return $cut === $s ? $s : $cut . '…';
}

$title = 'Sayt matnlari';
$section = (string) ($_GET['section'] ?? '');
$projectId = (string) ($_GET['id'] ?? '');
?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= app_h($title) ?> — <?= app_h($config['site_name'] ?? 'Forum') ?></title>
<link rel="stylesheet" href="admin.css">
<script src="admin.js" defer></script>
</head>
<body>
<header class="bar">
  <div>
    <strong>Saytni tahrirlash</strong>
    <span class="muted"><?= app_h($config['site_name'] ?? '') ?></span>
  </div>
  <nav class="bar__right">
    <a class="btn btn--quiet" href="./">Roʻyxatdan oʻtganlar</a>
    <a class="btn<?= $key === 'forum' ? '' : ' btn--quiet' ?>" href="site.php?f=forum">Forum sayti</a>
    <a class="btn<?= $key === 'content' ? '' : ' btn--quiet' ?>" href="site.php?f=content">Umumiy maʼlumotlar</a>
    <a class="btn btn--quiet" href="?action=logout">Chiqish</a>
  </nav>
</header>

<main>
<?php if ($problem !== ''): ?>
  <p class="err big"><?= app_h($problem) ?></p>
<?php endif; ?>
<?php if ($notice !== ''): ?>
  <p class="ok big"><?= app_h($notice) ?></p>
<?php endif; ?>
<?php if ($baseNote === 'redeployed'): ?>
  <p class="ok big">Saytning yangi versiyasi yuklangan ekan — oʻzgarishlaringiz uning ustiga qayta qoʻllandi.</p>
<?php endif; ?>
<?php if ($stale !== []): ?>
  <p class="err big">
    Quyidagi maydonlar saytning yangi versiyasida yoʻq, shuning uchun qoʻllanmadi:
    <?= app_h(implode(', ', array_slice($stale, 0, 8))) ?><?= count($stale) > 8 ? '…' : '' ?>
  </p>
<?php endif; ?>

<?php if ($base === null): ?>
  <p class="muted">Saytning maʼlumot fayli topilmadi. <code>server/.env</code> dagi
    <code>SITE_DIR</code> qiymatini tekshiring — u saytning <code>index.html</code> turgan papkasi
    boʻlishi kerak.</p>

<?php elseif ($page === 'projects' && $key === 'forum'): ?>
  <?php
  $projects = $merged['portfolio']['projects'] ?? [];
  $needle = app_lower(trim((string) ($_GET['q'] ?? '')));
  $hidden = array_flip($overrides['hidden']);
  $allProjects = $base['portfolio']['projects'] ?? [];
  // The region's own Uzbek name, out of the data rather than a second list
  // here: a table column saying "tashkent_city" is a database key on show.
  $regionNames = $merged['portfolio']['regionNames'] ?? [];
  ?>
  <nav class="crumbs"><a href="site.php?f=<?= app_h($key) ?>">Boʻlimlar</a> › Loyihalar</nav>
  <form class="filters" method="get">
    <input type="hidden" name="f" value="<?= app_h($key) ?>">
    <input type="hidden" name="page" value="projects">
    <input type="search" name="q" value="<?= app_h($_GET['q'] ?? '') ?>" placeholder="Loyiha nomi, hudud…">
    <button type="submit">Qidirish</button>
    <a class="btn btn--quiet" href="site.php?f=<?= app_h($key) ?>&amp;page=projects">Tozalash</a>
  </form>
  <p class="muted"><?= count($allProjects) ?> ta loyiha · saytda <?= count($projects) ?> ta koʻrinadi</p>
  <table class="rows">
    <thead><tr><th>Loyiha</th><th>Hudud</th><th>Yoʻnalish</th><th>Investitsiya</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($allProjects as $p):
        $id = (string) ($p['id'] ?? '');
        $name = (string) ($p['titleI18n']['uz'] ?? $p['displayTitle'] ?? $p['title'] ?? $id);
        $live = site_get($merged, "portfolio.projects.$id") ?? $p;
        $liveName = (string) ($live['titleI18n']['uz'] ?? $live['title'] ?? $name);
        if ($needle !== '' && !str_contains(app_lower($liveName . ' ' . $id), $needle)) {
            continue;
        }
        $isHidden = isset($hidden[$id]);
    ?>
      <tr<?= $isHidden ? ' class="row--off"' : '' ?>>
        <td>
          <a href="site.php?f=<?= app_h($key) ?>&amp;page=project&amp;id=<?= app_h($id) ?>"><b><?= app_h($liveName) ?></b></a>
          <?php if ($isHidden): ?><span class="chip chip--off">saytda yoʻq</span><?php endif; ?>
          <div class="muted small"><?= app_h($id) ?></div>
        </td>
        <td><?php $rg = (string) ($live['region'] ?? '');
          echo app_h($rg === '' ? '—' : ($regionNames[$rg]['uz'] ?? $rg)); ?></td>
        <td><?= app_h(SEGMENT_LABEL[(string) ($live['segment'] ?? '')] ?? (string) ($live['segment'] ?? '—')) ?></td>
        <td><?= app_h((string) ($live['investmentDisplay'] ?? '—')) ?></td>
        <td>
          <form method="post" class="inline">
            <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
            <input type="hidden" name="do" value="<?= $isHidden ? 'show' : 'hide' ?>">
            <input type="hidden" name="id" value="<?= app_h($id) ?>">
            <button class="btn btn--quiet" type="submit"><?= $isHidden ? 'Qaytarish' : 'Yashirish' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

<?php elseif ($page === 'project' && $key === 'forum' && $projectId !== ''): ?>
  <?php
  $p = site_get($base, "portfolio.projects.$projectId");
  $livep = site_get($merged, "portfolio.projects.$projectId");
  ?>
  <nav class="crumbs">
    <a href="site.php?f=<?= app_h($key) ?>">Boʻlimlar</a> ›
    <a href="site.php?f=<?= app_h($key) ?>&amp;page=projects">Loyihalar</a> ›
    <?= app_h((string) ($livep['titleI18n']['uz'] ?? $projectId)) ?>
  </nav>
  <?php if (!is_array($p)): ?>
    <p class="err big">Bunday loyiha yoʻq.</p>
  <?php else: ?>
    <form method="post" class="editor">
      <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
      <input type="hidden" name="do" value="save">
      <?php foreach ($p as $k => $v):
          if (in_array($k, ['id', '_photoKey', 'photos', 'sheets', 'issues'], true)) {
              continue;
          }
          node("portfolio.projects.$projectId.$k", label_for((string) $k), $v, $livep[$k] ?? null, $overrides);
      endforeach; ?>
      <div class="actions">
        <button type="submit">Saqlash va saytni yangilash</button>
        <a class="btn btn--quiet" href="site.php?f=<?= app_h($key) ?>&amp;page=projects">Bekor qilish</a>
      </div>
    </form>
  <?php endif; ?>

<?php elseif ($page === 'publish'): ?>
  <nav class="crumbs"><a href="site.php?f=<?= app_h($key) ?>">Boʻlimlar</a> › Nashr</nav>
  <section class="tiles">
    <div class="tile"><b><?= count($overrides['values']) ?></b><span>oʻzgartirilgan maydon</span></div>
    <div class="tile"><b><?= count($overrides['hidden']) ?></b><span>yashirilgan loyiha</span></div>
    <div class="tile"><b><?= $overrides['updated_count'] ?></b><span>marta saqlangan</span></div>
  </section>
  <p class="muted">
    Fayl: <code><?= app_h(site_published_file($config, $key)) ?></code><br>
    Oxirgi oʻzgarish: <?= app_h($overrides['updated_at'] !== '' ? $overrides['updated_at'] : '—') ?>
  </p>
  <?php if ($overrides['values'] !== []): ?>
    <table class="rows">
      <thead><tr><th>Maydon</th><th>Hozirgi qiymati</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($overrides['values'] as $path => $value): ?>
        <tr>
          <td class="small"><code><?= app_h((string) $path) ?></code></td>
          <td class="small"><?= app_h(mb_substr_safe(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE), 120)) ?></td>
          <td>
            <form method="post" class="inline">
              <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
              <input type="hidden" name="do" value="revert">
              <input type="hidden" name="path" value="<?= app_h((string) $path) ?>">
              <button class="btn btn--quiet" type="submit">Asl holiga</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <div class="actions">
    <form method="post" class="inline">
      <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
      <input type="hidden" name="do" value="republish">
      <button type="submit">Saytni qayta nashr etish</button>
    </form>
    <form method="post" class="inline" data-confirm="Barcha oʻzgarishlar bekor qilinadi. Davom etilsinmi?">
      <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
      <input type="hidden" name="do" value="revert-all">
      <button class="btn btn--quiet" type="submit">Hammasini asl holiga qaytarish</button>
    </form>
  </div>

<?php elseif ($section !== '' && isset($base[$section])): ?>
  <nav class="crumbs">
    <a href="site.php?f=<?= app_h($key) ?>">Boʻlimlar</a> › <?= app_h(label_for($section)) ?>
  </nav>
  <form method="post" class="editor">
    <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
    <input type="hidden" name="do" value="save">
    <?php node($section, label_for($section), $base[$section], $merged[$section] ?? null, $overrides); ?>
    <div class="actions">
      <button type="submit">Saqlash va saytni yangilash</button>
      <a class="btn btn--quiet" href="site.php?f=<?= app_h($key) ?>">Bekor qilish</a>
    </div>
  </form>

<?php else: ?>
  <p class="muted">
    Bu yerda oʻzgartirilgan narsa darhol saytga chiqadi — qayta qurish ham, deploy ham shart emas.
    Saytning yangi versiyasi yuklansa, oʻzgarishlaringiz uning ustiga oʻzi qayta qoʻllanadi.
  </p>
  <section class="cards">
    <?php if ($key === 'forum'): ?>
      <a class="card card--link" href="site.php?f=forum&amp;page=projects">
        <b>Loyihalar</b>
        <span><?= count($base['portfolio']['projects'] ?? []) ?> ta loyiha — tahrirlash, yashirish</span>
      </a>
    <?php endif; ?>
    <?php foreach ($base as $k => $v):
        if (in_array($k, ['portfolio'], true)) {
            continue;
        }
        $count = is_array($v) ? count($v) : 1;
        $edited = 0;
        foreach ($overrides['values'] as $path => $_) {
            if (str_starts_with((string) $path, $k . '.')) {
                $edited++;
            }
        }
    ?>
      <a class="card card--link" href="site.php?f=<?= app_h($key) ?>&amp;section=<?= app_h((string) $k) ?>">
        <b><?= app_h(label_for((string) $k)) ?></b>
        <span><?= $count ?> ta maydon<?= $edited ? " · {$edited} ta oʻzgartirilgan" : '' ?></span>
      </a>
    <?php endforeach; ?>
    <a class="card card--link" href="site.php?f=<?= app_h($key) ?>&amp;page=publish">
      <b>Nashr va tarix</b>
      <span><?= count($overrides['values']) ?> ta oʻzgarish · bekor qilish</span>
    </a>
  </section>
<?php endif; ?>
</main>
</body>
</html>
