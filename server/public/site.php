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

require_once __DIR__ . '/../src/adminui.php';
require_once __DIR__ . '/../src/site.php';
require_once __DIR__ . '/../src/photos.php';
require_once __DIR__ . '/../src/import.php';

$config = app_config();

/* The same headers, the same session and the same locks as the list; this
   page shows no telephone numbers but it can change what the site says. */
$user = admin_boot($config, 'site.edit');

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
/* The projects added from the admin are part of the base as far as the
   editor is concerned — an override has to have a path to land on, and a
   project added here is edited exactly like one that came out of a build. */
if ($base !== null) {
    $base = site_with_added($base, $overrides, $key);
}

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
                $refused = [];
                foreach ($fields as $path => $posted) {
                    $path = (string) $path;
                    if (!site_has($base, $path)) {
                        continue;
                    }
                    $original = site_get($base, $path);
                    $value = site_cast($original, is_string($posted) ? $posted : '');
                    /*
                     * Two fields that are keys, not words.
                     *
                     * `region` and `segment` are shown in the form as what
                     * they are — `surkhandarya`, `infrastructure` — because
                     * that is what the site reads them as: the region
                     * headings, the counts above the portfolio and the
                     * filter all look the value up. Typed over with the
                     * Uzbek name they would silently drop the project out of
                     * its own region. Now that more than one person can edit
                     * this, the two are checked rather than trusted.
                     */
                    $complaint = site_key_complaint($path, $value);
                    if ($complaint !== '') {
                        $refused[] = $complaint;
                        continue;
                    }
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
                if ($refused !== []) {
                    $problem = 'Saqlanmadi: ' . implode(' · ', $refused);
                }
                if ($changed || $cleared) {
                    audit_log($config, 'site.save', [
                        'fayl' => $key,
                        'oʻzgardi' => $changed,
                        'qaytdi' => $cleared,
                        'maydonlar' => array_slice(array_keys($fields), 0, 6),
                    ], (string) ($_GET['section'] ?? $_GET['id'] ?? $key));
                }
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
                audit_log($config, 'site.' . $do, [], $id);
                $overrides = site_overrides($config, $key);
            } elseif ($do === 'revert') {
                $path = (string) ($_POST['path'] ?? '');
                unset($overrides['values'][$path]);
                site_save_overrides($config, $key, $overrides);
                site_publish($config, $key);
                $notice = 'Maydon asl holiga qaytarildi.';
                audit_log($config, 'site.revert', ['fayl' => $key], $path);
                $overrides = site_overrides($config, $key);
            } elseif ($do === 'revert-all') {
                $undone = count($overrides['values']) + count($overrides['hidden']);
                $overrides['values'] = [];
                $overrides['hidden'] = [];
                site_save_overrides($config, $key, $overrides);
                site_publish($config, $key);
                $notice = 'Barcha oʻzgarishlar bekor qilindi — sayt asl holiga qaytdi.';
                audit_log($config, 'site.revert-all', ['fayl' => $key, 'bekor qilindi' => $undone]);
                $overrides = site_overrides($config, $key);
            } elseif ($do === 'republish') {
                $result = site_publish($config, $key);
                $notice = 'Sayt qayta nashr etildi: ' . $result['applied'] . ' ta oʻzgarish qoʻllandi.';
                audit_log($config, 'site.republish', ['fayl' => $key, 'oʻzgarish' => $result['applied']]);
                $overrides = site_overrides($config, $key);
            } elseif (str_starts_with($do, 'photo-')) {
                $notice = site_photo_action($config, $do, $overrides, $key);
                $overrides = site_overrides($config, $key);
            }
        } catch (Throwable $e) {
            $problem = $e->getMessage();
        }
        // Re-read the base: publishing may have re-taken it.
        $base = site_base($config, $key, $baseNote);
        if ($base !== null) {
            $base = site_with_added($base, $overrides, $key);
        }
    }
}

/**
 * Is this value allowed in this field?
 *
 * Only two fields are checked, and both for the same reason: they are keys
 * the site looks things up by, not text it prints. Everything else in the
 * editor is prose, a number or a date, and site_cast() already keeps each of
 * those in the shape the base held.
 */
function site_key_complaint(string $path, mixed $value): string
{
    if (!is_string($value) || !str_starts_with($path, 'portfolio.projects.')) {
        return '';
    }
    if (str_ends_with($path, '.region')) {
        $known = array_column(INTAKE_REGIONS, 'region');
        if (!in_array($value, $known, true)) {
            return '«Hudud» uchun «' . $value . '» notoʻgʻri. Ruxsat etilganlari: '
                . implode(', ', $known);
        }
    }
    if (str_ends_with($path, '.segment')) {
        $known = array_values(INTAKE_SEGMENTS);
        if (!in_array($value, $known, true)) {
            return '«Yoʻnalish» uchun «' . $value . '» notoʻgʻri. Ruxsat etilganlari: '
                . implode(', ', $known);
        }
    }
    return '';
}

/**
 * The photographs of one project: add, remove, reorder.
 *
 * A project's `photos` list is an override like any other, so all three are
 * one write to the overlay followed by a publish. The pictures themselves go
 * through src/photos.php, which re-encodes them and keeps the original in the
 * data directory where a deploy cannot delete it.
 *
 * Removing a photograph takes it off the project either way; the file is
 * deleted only when it is one of ours. A photograph that came out of a build
 * stays on disk, because the next deploy would bring it back anyway and a
 * file that disappears and returns is worse than one that is simply unused.
 */
function site_photo_action(array $config, string $do, array $overrides, string $key): string
{
    if ($key !== 'forum') {
        throw new RuntimeException('Rasmlar faqat forum sayti loyihalarida.');
    }
    $id = (string) ($_POST['id'] ?? '');
    /*
     * A project id and nothing else.
     *
     * It is pasted into a dotted path, and a dot inside it would be a second
     * segment: `sur-91.titleI18n` names a real place in the file and the
     * write would land on it. Every id the build produces, and every one the
     * importer makes, is lower-case letters, digits, a dash or an underscore.
     */
    if (preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/', $id) !== 1) {
        throw new RuntimeException('Loyiha kodi notoʻgʻri.');
    }
    $path = 'portfolio.projects.' . $id . '.photos';
    $base = site_base($config, $key);
    if ($base === null) {
        throw new RuntimeException('Saytning maʼlumot fayli topilmadi.');
    }
    $base = site_with_added($base, $overrides, $key);
    if (!site_has($base, 'portfolio.projects.' . $id)) {
        throw new RuntimeException('Bunday loyiha yoʻq: ' . $id);
    }
    $current = $overrides['values'][$path] ?? site_get($base, $path);
    $list = [];
    foreach ((array) $current as $stem) {
        if (is_string($stem) && photos_stem_ok($stem)) {
            $list[] = $stem;
        }
    }

    $message = '';
    if ($do === 'photo-add') {
        $file = $_FILES['photo'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Rasm yuklanmadi — fayl tanlanmagan yoki serverning chegarasidan katta.');
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Yuklangan fayl topilmadi.');
        }
        if (count($list) >= PHOTO_MAX_PER_PROJECT) {
            throw new RuntimeException('Bitta loyihaga ' . PHOTO_MAX_PER_PROJECT . ' tadan koʻp rasm qoʻyilmaydi.');
        }
        $bytes = (string) @file_get_contents((string) $file['tmp_name']);
        $stem = photos_store($config, $bytes, $id);
        $list[] = $stem;
        audit_log($config, 'photo.add', ['rasm' => $stem, 'jami' => count($list)], $id);
        $message = 'Rasm qoʻshildi.';
    } elseif ($do === 'photo-remove') {
        $stem = (string) ($_POST['stem'] ?? '');
        $list = array_values(array_filter($list, static fn(string $s): bool => $s !== $stem));
        if (photos_is_own($config, $stem)) {
            photos_delete($config, $stem);
        }
        audit_log($config, 'photo.remove', ['rasm' => $stem, 'qoldi' => count($list)], $id);
        $message = 'Rasm olib tashlandi.';
    } elseif ($do === 'photo-move') {
        $stem = (string) ($_POST['stem'] ?? '');
        $step = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
        $at = array_search($stem, $list, true);
        $to = $at === false ? -1 : $at + $step;
        if ($at !== false && $to >= 0 && $to < count($list)) {
            [$list[$at], $list[$to]] = [$list[$to], $list[$at]];
            audit_log($config, 'photo.order', ['rasm' => $stem, 'oʻrin' => $to + 1], $id);
            $message = 'Tartib oʻzgartirildi.';
        }
    }

    $original = site_get($base, $path);
    if (is_array($original) && $original === $list) {
        unset($overrides['values'][$path]);
    } else {
        $overrides['values'][$path] = $list;
    }
    site_save_overrides($config, $key, $overrides);
    site_publish($config, $key);
    return $message;
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
<?php
admin_head($config, $user, 'Saytni tahrirlash', 'site');
?>
<div class="subbar">
  <?php
  /*
   * Which of the two files is open is the one thing this strip has to say,
   * so that is the button that is filled in. The doors to the other rooms
   * are in the bar above it.
   */
  ?>
  <a class="btn<?= $key === 'forum' ? ' btn--lead' : '' ?>" href="site.php?f=forum">Forum sayti</a>
  <a class="btn<?= $key === 'content' ? ' btn--lead' : '' ?>" href="site.php?f=content">Umumiy maʼlumotlar</a>
  <span class="subbar__spacer"></span>
  <a class="btn" href="site.php?f=<?= app_h($key) ?>&amp;page=publish">Nashr va tarix</a>
</div>

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
  <p class="muted count"><?= count($allProjects) ?> ta loyiha · saytda <?= count($projects) ?> ta koʻrinadi</p>
  <?php
  /*
   * Two hundred rows under one heading is a list nobody can find anything
   * in, so the table is cut into its regions — which is the order the
   * portfolio is already in — and each region gets a heading row carrying
   * its own count. The rows are gathered first because that count cannot be
   * known until the search has taken its share out.
   */
  $shown = [];
  foreach ($allProjects as $p) {
      $id = (string) ($p['id'] ?? '');
      $name = (string) ($p['titleI18n']['uz'] ?? $p['displayTitle'] ?? $p['title'] ?? $id);
      $live = site_get($merged, "portfolio.projects.$id") ?? $p;
      $liveName = (string) ($live['titleI18n']['uz'] ?? $live['title'] ?? $name);
      if ($needle !== '' && !str_contains(app_lower($liveName . ' ' . $id), $needle)) {
          continue;
      }
      $rg = (string) ($live['region'] ?? '');
      $shown[] = ['id' => $id, 'live' => $live, 'name' => $liveName, 'region' => $rg];
  }
  $perRegion = [];
  foreach ($shown as $s) {
      $perRegion[$s['region']] = ($perRegion[$s['region']] ?? 0) + 1;
  }
  $regionOf = static fn (string $rg): string => $rg === ''
      ? 'Hududsiz'
      : (string) ($regionNames[$rg]['uz'] ?? $rg);
  $lastRegion = null;
  /* The regions in the order the table puts them, for the jump bar. */
  $regionOrder = [];
  foreach ($shown as $s) {
      if (!in_array($s['region'], $regionOrder, true)) {
          $regionOrder[] = $s['region'];
      }
  }
  ?>
  <?php if (count($regionOrder) > 1): ?>
  <nav class="jump" aria-label="Hududlar">
    <?php foreach ($regionOrder as $rg): ?>
      <a href="#r-<?= app_h($rg === '' ? 'none' : $rg) ?>"><b><?= app_h($regionOf($rg)) ?></b> <i><?= (int) $perRegion[$rg] ?></i></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <table class="rows">
    <?php /* No region column: every row under a heading is in that region,
             and saying so two hundred times took a column's width to repeat
             what the heading above it already says. */ ?>
    <thead><tr><th>Loyiha</th><th>Yoʻnalish</th><th>Investitsiya</th><th>Rasm</th><th></th></tr></thead>
    <?php
    /*
     * One `tbody` per region, and not one for the whole table.
     *
     * The heading rows are sticky, and a sticky element stops sticking when
     * its own containing block leaves the screen. In a single `tbody` that
     * block is the whole two hundred rows, so every heading scrolled past
     * stayed pinned and they piled up on each other — by Khorezm there were
     * fourteen region names stacked under the column heads. A `tbody` per
     * region gives each heading exactly its own rows to be stuck to.
     */
    ?>
    <?php foreach ($shown as $s):
        $id = $s['id'];
        $live = $s['live'];
        $liveName = $s['name'];
        $isHidden = isset($hidden[$id]);
        if ($s['region'] !== $lastRegion):
            if ($lastRegion !== null): ?></tbody><?php endif;
            $lastRegion = $s['region'];
    ?>
      <tbody>
      <tr class="rowgroup"><th colspan="5" scope="colgroup" id="r-<?= app_h($s['region'] === '' ? 'none' : $s['region']) ?>">
        <?= app_h($regionOf($s['region'])) ?><span><?= (int) $perRegion[$s['region']] ?> ta</span>
      </th></tr>
    <?php endif; ?>
      <tr<?= $isHidden ? ' class="row--off"' : '' ?>>
        <td>
          <a href="site.php?f=<?= app_h($key) ?>&amp;page=project&amp;id=<?= app_h($id) ?>"><b><?= app_h($liveName) ?></b></a>
          <?php if ($isHidden): ?><span class="chip chip--off">saytda yoʻq</span><?php endif; ?>
          <?php if (site_is_added($overrides, $id)): ?><span class="chip chip--new">adminkadan</span><?php endif; ?>
          <div class="muted small"><?= app_h($id) ?></div>
        </td>
        <td><?= app_h(SEGMENT_LABEL[(string) ($live['segment'] ?? '')] ?? (string) ($live['segment'] ?? '—')) ?></td>
        <td><?= app_h((string) ($live['investmentDisplay'] ?? '—')) ?></td>
        <td class="num"><?php $n = count((array) ($live['photos'] ?? [])); echo $n > 0 ? $n : '—'; ?></td>
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
    <?php if ($lastRegion !== null): ?></tbody><?php endif; ?>
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
    <?php
    /*
     * The photographs come first, above the fields.
     *
     * They are what the organiser opens this page to change — a project
     * published without a picture reads as a spreadsheet row — and they are
     * the one thing here that is not a text box, so they would be lost at the
     * bottom of thirty fields.
     */
    $shots = [];
    foreach ((array) ($livep['photos'] ?? []) as $stem) {
        if (is_string($stem) && photos_stem_ok($stem)) {
            $shots[] = $stem;
        }
    }
    ?>
    <section class="shots">
      <h2>Rasmlar <span class="muted"><?= count($shots) ?> / <?= PHOTO_MAX_PER_PROJECT ?></span></h2>
      <?php if ($shots === []): ?>
        <p class="muted">Bu loyihada hali rasm yoʻq. Saytda rasm oʻrnida chizma koʻrinadi.</p>
      <?php endif; ?>
      <ol class="shots__list">
        <?php foreach ($shots as $i => $stem): $mine = photos_is_own($config, $stem); ?>
          <li class="shot">
            <?php if (photos_present($config, $stem)): ?>
              <img src="<?= app_h(site_photo_url($config, $stem)) ?>" alt="" loading="lazy" width="110" height="80">
            <?php else: ?>
              <span class="shot__gone" title="<?= app_h($stem) ?>">fayl yoʻq</span>
            <?php endif; ?>
            <div class="shot__bar">
              <span class="shot__n"><?= $i + 1 ?></span>
              <?php foreach (['up' => '↑', 'down' => '↓'] as $dir => $arrow): ?>
                <form method="post" class="inline">
                  <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
                  <input type="hidden" name="do" value="photo-move">
                  <input type="hidden" name="id" value="<?= app_h($projectId) ?>">
                  <input type="hidden" name="stem" value="<?= app_h($stem) ?>">
                  <input type="hidden" name="dir" value="<?= $dir ?>">
                  <button class="btn btn--tiny" type="submit" title="<?= $dir === 'up' ? 'Oldinga' : 'Orqaga' ?>"
                          <?= ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($shots) - 1) ? 'disabled' : '' ?>><?= $arrow ?></button>
                </form>
              <?php endforeach; ?>
              <form method="post" class="inline" data-confirm="Rasm olib tashlansinmi?">
                <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
                <input type="hidden" name="do" value="photo-remove">
                <input type="hidden" name="id" value="<?= app_h($projectId) ?>">
                <input type="hidden" name="stem" value="<?= app_h($stem) ?>">
                <button class="btn btn--tiny btn--danger" type="submit" title="Olib tashlash">×</button>
              </form>
            </div>
            <?php if (!$mine): ?><span class="chip chip--quiet">build dan</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php if (count($shots) < PHOTO_MAX_PER_PROJECT): ?>
        <?php if (!photos_can()): ?>
          <p class="warn"><?= app_h(photos_complaint_server()) ?></p>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="shots__add">
          <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
          <input type="hidden" name="do" value="photo-add">
          <input type="hidden" name="id" value="<?= app_h($projectId) ?>">
          <label for="photo">Yangi rasm</label>
          <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
          <button type="submit">Yuklash</button>
          <small>JPEG, PNG yoki WebP. Eng kattasi <?= app_h(photos_say_size(PHOTO_MAX_BYTES)) ?>.
            Rasm serverda WebP ga aylantiriladi, EXIF maʼlumotlari olib tashlanadi.</small>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

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
  <p class="muted lede">
    Bu yerda oʻzgartirilgan narsa darhol saytga chiqadi — qayta qurish ham, deploy ham shart emas.
    Saytning yangi versiyasi yuklansa, oʻzgarishlaringiz uning ustiga oʻzi qayta qoʻllanadi.
  </p>
  <?php
  /*
   * Two of these are not sections of the file. The portfolio is where the
   * organiser spends their time, and publishing is the only thing on the
   * page that leaves the server; among sixteen cards of equal weight both
   * were invisible, so they stand above the sixteen rather than inside them.
   */
  ?>
  <section class="cards cards--lead">
    <?php if ($key === 'forum'): ?>
      <a class="card card--link" href="site.php?f=forum&amp;page=projects">
        <b>Loyihalar</b>
        <span><?= count($base['portfolio']['projects'] ?? []) ?> ta loyiha — tahrirlash, yashirish</span>
      </a>
    <?php endif; ?>
    <a class="card card--link" href="site.php?f=<?= app_h($key) ?>&amp;page=publish">
      <b>Nashr va tarix</b>
      <span><?= count($overrides['values']) ?> ta oʻzgarish · bekor qilish</span>
    </a>
  </section>

  <p class="group-head">Sayt matnlari</p>
  <section class="cards">
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
  </section>
<?php endif; ?>
</main>
<?php
admin_foot();
