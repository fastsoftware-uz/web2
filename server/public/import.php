<?php
/**
 * Adding projects to the published portfolio, from the admin.
 *
 * Three ways in, all ending in the same place — a project record appended to
 * the overlay and published, exactly as src/site.php stores every other
 * change:
 *
 *   a filled-in list      .xlsx or .csv, the template the regions use;
 *   a folder of pictures  .zip, file names carrying the project codes;
 *   one project by hand   the same columns as a form, for a single building.
 *
 * ## Nothing is written until somebody has looked
 *
 * An upload is staged in the data directory and read into a report: which
 * rows are new, which are refused and why, and — the reason this page exists
 * at all — which ones are already on the site. The portfolio is two hundred
 * and sixty projects assembled from decks, regional lists and committee
 * spreadsheets, and the same hotel has arrived under three different names
 * already. A list of nine rows sent in on 13 November held seven projects
 * that were published weeks earlier.
 *
 * So the report is the page, and the writing is a second request that says
 * which rows the operator agreed to. A row that collides outright cannot be
 * chosen at all; a row that merely resembles something is shown beside what
 * it resembles and starts unticked.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/adminui.php';
require_once __DIR__ . '/../src/import.php';
require_once __DIR__ . '/../src/photos.php';

$config = app_config();
$user = admin_boot($config, 'projects.add');

/* ------------------------------------------------------------- the staging */

/**
 * Where an upload waits while somebody reads the report.
 *
 * Inside the data directory, which is outside the web root and excluded from
 * every deploy. The token is random and is remembered in the session, so one
 * reader cannot open another's upload by guessing a name — and the whole
 * folder is removed as soon as it has been used or an hour has passed.
 */
function import_job_dir(array $config, string $token): string
{
    return rtrim((string) $config['data_dir'], '/\\') . '/import/' . $token;
}

function import_job_valid(string $token): bool
{
    return preg_match('/^[a-f0-9]{24}$/', $token) === 1
        && isset($_SESSION['import_jobs'][$token]);
}

function import_job_forget(array $config, string $token): void
{
    $dir = import_job_dir($config, $token);
    foreach ((array) @scandir($dir) as $name) {
        if (is_string($name) && $name !== '.' && $name !== '..') {
            @unlink($dir . '/' . $name);
        }
    }
    @rmdir($dir);
    unset($_SESSION['import_jobs'][$token]);
}

/** Throw away anything left behind by a reader who closed the tab. */
function import_sweep(array $config): void
{
    $root = rtrim((string) $config['data_dir'], '/\\') . '/import';
    if (!is_dir($root)) {
        return;
    }
    foreach ((array) @scandir($root) as $name) {
        if (!is_string($name) || preg_match('/^[a-f0-9]{24}$/', $name) !== 1) {
            continue;
        }
        $dir = $root . '/' . $name;
        if ((int) @filemtime($dir) < time() - 7200) {
            foreach ((array) @scandir($dir) as $file) {
                if (is_string($file) && $file !== '.' && $file !== '..') {
                    @unlink($dir . '/' . $file);
                }
            }
            @rmdir($dir);
            unset($_SESSION['import_jobs'][$name]);
        }
    }
}

import_sweep($config);

/** What the server will take, in bytes, with PHP's own limits respected. */
function import_limit(array $config): int
{
    $asked = max(1, (int) ($config['upload_max_mb'] ?? 32)) * 1048576;
    foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
        $value = (string) ini_get($setting);
        $n = (int) $value;
        $unit = strtoupper(substr(trim($value), -1));
        $n *= ['K' => 1024, 'M' => 1048576, 'G' => 1073741824][$unit] ?? 1;
        if ($n > 0) {
            $asked = min($asked, $n);
        }
    }
    return $asked;
}

/** A sentence for whatever PHP says went wrong with an upload. */
function import_upload_complaint(int $error, int $limit): string
{
    return match ($error) {
        UPLOAD_ERR_OK => '',
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fayl serverning chegarasidan katta ('
            . photos_say_size($limit) . '). nginx va php.ini dagi chegarani oshiring — '
            . 'server/README.md ichida yozilgan.',
        UPLOAD_ERR_PARTIAL => 'Fayl toʻliq yuklanmadi — qaytadan urinib koʻring.',
        UPLOAD_ERR_NO_FILE => 'Fayl tanlanmagan.',
        UPLOAD_ERR_NO_TMP_DIR => 'Serverda vaqtinchalik papka yoʻq — administratorga ayting.',
        UPLOAD_ERR_CANT_WRITE => 'Serverga yozib boʻlmadi — administratorga ayting.',
        UPLOAD_ERR_EXTENSION => 'Yuklashni PHP kengaytmasi toʻxtatdi.',
        default => 'Faylni yuklab boʻlmadi.',
    };
}

/* ------------------------------------------------------- the published list */

$baseNote = null;
$base = site_base($config, 'forum', $baseNote);
$overrides = site_overrides($config, 'forum');
$published = $base === null ? [] : site_with_added($base, $overrides, 'forum')['portfolio']['projects'] ?? [];

$problem = '';
$page = (string) ($_GET['page'] ?? '');
$job = (string) ($_GET['job'] ?? '');

/* ------------------------------------------------------- the server's probe */

/**
 * "Will this server take a file at all?"
 *
 * nginx refuses a body larger than `client_max_body_size` before PHP is
 * reached, and its answer is a bare 413 that a form post shows as a blank
 * page. That looked like a broken admin twice before this existed. The page
 * sends a body of a known size here and reports what came back, which turns
 * an invisible server setting into one sentence with the fix in it.
 */
if (($_GET['probe'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!admin_check_token_header()) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'bytes' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
        'limit' => import_limit($config),
    ]);
    exit;
}

/* ------------------------------------------------------------- the template */

if (($_GET['action'] ?? '') === 'template') {
    admin_csv_start('loyihalar-namuna.csv');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    $headers = [];
    $example = [];
    foreach (import_columns() as $column) {
        $headers[] = $column['header'] . (!empty($column['required']) ? ' *' : '');
        $example[] = admin_csv_cell(match ($column['key']) {
            'code' => 'SUR-01',
            'region' => 'Surxondaryo viloyati',
            'district' => 'Termiz shahri',
            'title_uz' => 'Termiz boʻyida mehmonxona',
            'title_ru' => 'Гостиница в Термезе',
            'title_en' => 'A hotel in Termez',
            'segment' => 'Mehmonxona va infratuzilma',
            'activity' => 'Mehmonxona',
            'stage' => 'Yangi qurilish (greenfield)',
            'address' => 'Alisher Navoiy koʻchasi, 12',
            'coords' => '37.2242, 67.2783',
            'amount' => '4500000',
            'currency' => 'USD',
            'amount_note' => 'Summa aniq',
            'land' => '0.8',
            'floor_area' => '3200',
            'capacity' => '64 xona',
            'jobs' => '45',
            'payback' => '6 yil',
            'period' => '24 oy',
            'utilities' => 'Bor',
            'access' => '6 / 3',
            'holder' => 'Termiz shahar hokimligi',
            'description' => 'Uch-besh gap: nima quriladi, kim uchun, nimasi bilan ajralib turadi.',
            'why' => 'Bitta kuchli dalil: turistlar oqimi, yaqin mehmonxonalar narxi.',
            'photos' => '2',
            'owner_org' => 'Termiz shahar hokimligi',
            'owner_contact' => '+998 76 221 10 10',
            'source' => 'Qaror 123, 10.11.2026',
            'note' => 'namuna qator — oʻchirib tashlang',
            default => '',
        });
    }
    fputcsv($out, $headers, ';', '"', '');
    fputcsv($out, $example, ';', '"', '');
    fclose($out);
    exit;
}

/* ----------------------------------------------------------------- actions */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = (string) ($_POST['do'] ?? '');
    if (!admin_check_token()) {
        $problem = 'Sahifa eskirgan — qaytadan urinib koʻring.';
    } else {
        try {
            if ($do === 'upload') {
                $job = import_do_upload($config);
                header('Location: import.php?job=' . urlencode($job));
                exit;
            }
            if ($do === 'cancel') {
                $token = (string) ($_POST['job'] ?? '');
                if (import_job_valid($token)) {
                    import_job_forget($config, $token);
                }
                header('Location: import.php');
                exit;
            }
            if ($do === 'commit') {
                import_do_commit($config, $published);
                header('Location: import.php');
                exit;
            }
            if ($do === 'manual') {
                import_do_manual($config, $published);
                header('Location: import.php');
                exit;
            }
            if ($do === 'remove') {
                $id = (string) ($_POST['id'] ?? '');
                if (site_remove_added($config, $id)) {
                    site_publish($config, 'forum');
                    audit_log($config, 'project.remove', ['id' => $id], $id);
                    admin_flash('ok', 'Loyiha saytdan olib tashlandi: ' . $id);
                } else {
                    admin_flash('err', 'Bu loyiha adminkadan qoʻshilmagan — uni faqat yashirish mumkin.');
                }
                header('Location: import.php');
                exit;
            }
        } catch (Throwable $e) {
            $problem = $e->getMessage();
        }
    }
}

/**
 * Take the uploaded files and put them somewhere a second request can read.
 *
 * @return string the job token
 */
function import_do_upload(array $config): string
{
    $limit = import_limit($config);
    $list = $_FILES['list'] ?? null;
    if (!is_array($list) || (int) ($list['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(import_upload_complaint(
            is_array($list) ? (int) $list['error'] : UPLOAD_ERR_NO_FILE,
            $limit,
        ));
    }
    if (!is_uploaded_file((string) $list['tmp_name'])) {
        throw new RuntimeException('Yuklangan fayl topilmadi.');
    }
    if ((int) $list['size'] > $limit) {
        throw new RuntimeException('Roʻyxat fayli juda katta: ' . photos_say_size((int) $list['size']));
    }

    $token = bin2hex(random_bytes(12));
    $dir = import_job_dir($config, $token);
    if (!is_dir($dir) && !@mkdir($dir, 0o770, true) && !is_dir($dir)) {
        throw new RuntimeException('Vaqtinchalik papkani yaratib boʻlmadi: ' . $dir);
    }
    if (!@move_uploaded_file((string) $list['tmp_name'], $dir . '/list.bin')) {
        throw new RuntimeException('Faylni saqlab boʻlmadi.');
    }
    @chmod($dir . '/list.bin', 0o600);
    /*
     * The file's own name is kept, and it is kept clean.
     *
     * It ends up in the project's `sheets` list, which is published — it is
     * how somebody later works out which spreadsheet a figure came from. A
     * name is whatever the sender typed, so it is reduced to the characters
     * a file name is made of before it goes anywhere near the site's data.
     */
    $given = (string) ($list['name'] ?? '');
    $clean = trim((string) preg_replace('/[^\p{L}\p{N}._ ()-]+/u', ' ', $given));
    $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));
    @file_put_contents($dir . '/name.txt', app_cut($clean === '' ? 'adminka' : $clean, 90));

    $zip = $_FILES['photos'] ?? null;
    if (is_array($zip) && (int) ($zip['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        if ((int) $zip['size'] > $limit) {
            throw new RuntimeException('Rasmlar arxivi juda katta: ' . photos_say_size((int) $zip['size']));
        }
        if (strncmp((string) @file_get_contents((string) $zip['tmp_name'], false, null, 0, 2), 'PK', 2) !== 0) {
            throw new RuntimeException('Rasmlar fayli .zip emas.');
        }
        if (@move_uploaded_file((string) $zip['tmp_name'], $dir . '/photos.zip')) {
            @chmod($dir . '/photos.zip', 0o600);
        }
    } elseif (is_array($zip) && (int) $zip['error'] !== UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Rasmlar arxivi: ' . import_upload_complaint((int) $zip['error'], $limit));
    }

    $_SESSION['import_jobs'][$token] = time();
    return $token;
}

/** Write the rows the operator ticked. */
function import_do_commit(array $config, array $published): void
{
    $token = (string) ($_POST['job'] ?? '');
    if (!import_job_valid($token)) {
        throw new RuntimeException('Yuklangan fayl topilmadi — qaytadan yuklang.');
    }
    $dir = import_job_dir($config, $token);
    $name = (string) @file_get_contents($dir . '/name.txt');
    $report = import_report($dir . '/list.bin', $name !== '' ? $name : 'adminka', $published);

    $wanted = [];
    foreach ((array) ($_POST['take'] ?? []) as $code) {
        $wanted[strtoupper((string) $code)] = true;
    }

    $zip = is_file($dir . '/photos.zip') ? $dir . '/photos.zip' : '';
    $codes = array_map(static fn(array $c): string => $c['code'], $report['candidates']);
    $matched = $zip !== '' ? import_match_photos($zip, $codes) : ['byCode' => []];

    $added = 0;
    $photos = 0;
    $photoProblems = [];
    foreach ($report['candidates'] as $candidate) {
        if ($candidate['state'] === 'duplicate' || !isset($wanted[$candidate['code']])) {
            continue;
        }
        $project = $candidate['project'];
        foreach ($matched['byCode'][$candidate['code']] ?? [] as $picture) {
            if (count($project['photos']) >= PHOTO_MAX_PER_PROJECT) {
                break;
            }
            try {
                $bytes = zip_get($zip, $picture['name']);
                if ($bytes === null) {
                    throw new RuntimeException('arxivdan oʻqib boʻlmadi');
                }
                $project['photos'][] = photos_store($config, $bytes, $candidate['code']);
                $photos++;
            } catch (Throwable $e) {
                $photoProblems[] = $picture['base'] . ': ' . $e->getMessage();
            }
        }
        site_add_project($config, $project);
        audit_log($config, 'project.add', [
            'nomi' => (string) $project['titleI18n']['uz'],
            'hudud' => (string) $project['region'],
            'rasm' => count($project['photos']),
            'manba' => $name,
        ], (string) $project['id']);
        $added++;
    }

    if ($added > 0) {
        site_publish($config, 'forum');
    }
    import_job_forget($config, $token);

    audit_log($config, 'project.import', [
        'fayl' => $name,
        'qoʻshildi' => $added,
        'rasm' => $photos,
        'rad etildi' => count($report['refused']),
        'dublikat' => $report['counts']['duplicate'],
    ]);

    $message = $added === 0
        ? 'Hech narsa qoʻshilmadi — birorta qator belgilanmagan edi.'
        : $added . ' ta loyiha qoʻshildi' . ($photos ? ', ' . $photos . ' ta rasm bilan' : '') . '. Sayt yangilandi.';
    if ($photoProblems !== []) {
        $message .= ' Rasmlardan ' . count($photoProblems) . ' tasi olinmadi: '
            . implode('; ', array_slice($photoProblems, 0, 3));
    }
    admin_flash($added === 0 ? 'err' : 'ok', $message);
}

/** One project, typed into the form rather than sent as a file. */
function import_do_manual(array $config, array $published): void
{
    $values = [];
    foreach (import_columns() as $column) {
        $values[$column['key']] = app_str($_POST, 'f_' . $column['key'], 1600);
    }
    $seen = [];
    $checked = import_check_row($values, 0, $seen);
    if (!$checked['ok']) {
        throw new RuntimeException('Toʻgʻrilash kerak: ' . implode(' · ', $checked['errors']));
    }
    $project = import_to_project($checked['row'], 'adminka');
    $clashes = import_clashes($project, import_index($published));
    foreach ($clashes as $clash) {
        if ($clash['level'] === 'stop') {
            throw new RuntimeException(
                'Bu loyiha allaqachon saytda: ' . $clash['title'] . ' (' . $clash['id'] . '). ' . $clash['why'],
            );
        }
    }
    if ($clashes !== [] && ($_POST['anyway'] ?? '') !== '1') {
        $words = [];
        foreach ($clashes as $clash) {
            $words[] = $clash['why'] . ' — ' . $clash['title'] . ' (' . $clash['id'] . ')';
        }
        throw new RuntimeException(
            'Oʻxshash loyiha bor: ' . implode('; ', $words)
            . '. Baribir qoʻshmoqchi boʻlsangiz, pastdagi belgini qoʻying.',
        );
    }

    site_add_project($config, $project);
    site_publish($config, 'forum');
    audit_log($config, 'project.add', [
        'nomi' => (string) $project['titleI18n']['uz'],
        'hudud' => (string) $project['region'],
        'usul' => 'qoʻlda',
        'ogohlantirish' => count($clashes),
    ], (string) $project['id']);
    admin_flash('ok', 'Loyiha qoʻshildi: ' . $project['titleI18n']['uz'] . '. Sayt yangilandi.');
}

/* --------------------------------------------------------------- rendering */

$report = null;
if ($job !== '' && import_job_valid($job)) {
    $dir = import_job_dir($config, $job);
    $sourceName = (string) @file_get_contents($dir . '/name.txt');
    try {
        $report = import_report($dir . '/list.bin', $sourceName !== '' ? $sourceName : 'adminka', $published);
        if (is_file($dir . '/photos.zip')) {
            $codes = array_map(static fn(array $c): string => $c['code'], $report['candidates']);
            $report['photos'] = import_match_photos($dir . '/photos.zip', $codes);
        }
    } catch (Throwable $e) {
        $problem = $e->getMessage();
        $report = null;
    }
} elseif ($job !== '') {
    $problem = 'Yuklangan fayl topilmadi — qaytadan yuklang.';
}

$ours = $overrides['added'];
$limit = import_limit($config);

/*
 * A region's own Uzbek name, out of the site's data rather than a second
 * list here: a table column reading "tashkent_city" is a database key on
 * show, and the portfolio already carries the names it publishes.
 */
$regionNames = $base['portfolio']['regionNames'] ?? [];
$regionOf = static function (string $key) use ($regionNames): string {
    if ($key === '') {
        return '—';
    }
    return (string) ($regionNames[$key]['uz'] ?? INTAKE_REGIONS[strtoupper(substr($key, 0, 3))]['uz'] ?? $key);
};

admin_head($config, $user, 'Loyiha qoʻshish', 'import');
?>
<main class="wide">
<?php admin_flash_print(); ?>
<?php if ($problem !== ''): ?>
  <p class="err big"><?= app_h($problem) ?></p>
<?php endif; ?>
<?php if ($base === null): ?>
  <p class="err big">Saytning maʼlumot fayli topilmadi — <code>server/.env</code> dagi <code>SITE_DIR</code> ni tekshiring.</p>
<?php endif; ?>

<?php if ($report !== null): /* ------------------------------ the report --- */ ?>
  <nav class="crumbs"><a href="import.php">Loyiha qoʻshish</a> › Tekshiruv natijasi</nav>

  <section class="tiles">
    <div class="tile tile--good"><b><?= (int) $report['counts']['new'] ?></b><span>yangi loyiha</span></div>
    <div class="tile tile--warn"><b><?= (int) $report['counts']['ask'] ?></b><span>oʻxshashi bor</span></div>
    <div class="tile"><b><?= (int) $report['counts']['duplicate'] ?></b><span>allaqachon saytda</span></div>
    <div class="tile tile--bad"><b><?= (int) $report['counts']['refused'] ?></b><span>xatolik bor</span></div>
  </section>

  <p class="muted">
    Varaq: <b><?= app_h($report['sheet']) ?></b> · sarlavha <?= (int) $report['header_row'] ?>-qatorda
    <?php if ($report['skipped'] > 0): ?> · <?= (int) $report['skipped'] ?> ta namuna qator oʻtkazib yuborildi<?php endif; ?>
    <?php if (isset($report['photos'])): ?>
      · rasmlar: <?= array_sum(array_map('count', $report['photos']['byCode'])) ?> ta mos keldi
      <?php if ($report['photos']['orphans'] !== []): ?>, <?= count($report['photos']['orphans']) ?> tasi hech qaysi qatorga tegishli emas<?php endif; ?>
    <?php endif; ?>
  </p>

  <?php if ($report['missing_columns'] !== []): ?>
    <p class="err big">Faylda yetishmayotgan majburiy ustunlar:
      <?= app_h(implode(' · ', $report['missing_columns'])) ?></p>
  <?php endif; ?>

  <?php if ($report['refused'] !== []): ?>
    <h2 class="group-head">Qoʻshilmaydigan qatorlar — avval tuzating</h2>
    <table class="rows rows--refused">
      <thead><tr><th>Qator</th><th>Kod</th><th>Nomi</th><th>Nima notoʻgʻri</th></tr></thead>
      <tbody>
      <?php foreach ($report['refused'] as $bad): ?>
        <tr class="row--bad">
          <td class="num"><?= (int) $bad['row'] ?></td>
          <td class="nowrap"><?= app_h($bad['code']) ?></td>
          <td><?= app_h($bad['title']) ?></td>
          <td><ul class="why"><?php foreach ($bad['errors'] as $why): ?><li><?= app_h($why) ?></li><?php endforeach; ?></ul></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" class="report">
    <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
    <input type="hidden" name="do" value="commit">
    <input type="hidden" name="job" value="<?= app_h($job) ?>">

    <?php if ($report['candidates'] === []): ?>
      <p class="empty">Faylda qoʻshsa boʻladigan qator topilmadi.</p>
    <?php else: ?>
    <h2 class="group-head">Qatorlar</h2>
    <p class="muted lede">
      Yangi loyihalar belgilangan. Oʻxshashi topilganlari belgilanmagan — yonidagi
      loyihani ochib solishtiring, ikkitasi bitta obyekt boʻlsa belgilamang.
      Allaqachon saytda turganlarini qoʻshib boʻlmaydi.
    </p>
    <table class="rows rows--pick">
      <thead>
        <tr><th></th><th>Kod</th><th>Loyiha</th><th>Hudud</th><th>Investitsiya</th><th>Rasm</th><th>Holat</th></tr>
      </thead>
      <tbody>
      <?php foreach ($report['candidates'] as $c):
          $p = $c['project'];
          $pictures = count($report['photos']['byCode'][$c['code']] ?? []);
          $rowClass = ['new' => '', 'ask' => 'row--warn', 'duplicate' => 'row--off'][$c['state']];
      ?>
        <tr class="<?= $rowClass ?>">
          <td class="pick">
            <?php if ($c['state'] === 'duplicate'): ?>
              <span class="chip chip--off">yoʻq</span>
            <?php else: ?>
              <input type="checkbox" name="take[]" value="<?= app_h($c['code']) ?>"
                     id="t<?= app_h($c['code']) ?>" <?= $c['state'] === 'new' ? 'checked' : '' ?>>
            <?php endif; ?>
          </td>
          <td class="nowrap"><label for="t<?= app_h($c['code']) ?>"><?= app_h($c['code']) ?></label></td>
          <td>
            <b><?= app_h((string) $p['titleI18n']['uz']) ?></b>
            <?php if (($p['subtitle'] ?? '') !== ''): ?>
              <div class="muted small"><?= app_h((string) $p['subtitle']) ?></div>
            <?php endif; ?>
            <?php foreach ($c['clashes'] as $clash): ?>
              <div class="clash">
                <?= app_h($clash['why']) ?>
                <a href="site.php?f=forum&amp;page=project&amp;id=<?= app_h($clash['id']) ?>" target="_blank" rel="noopener"><?= app_h($clash['title']) ?></a>
              </div>
            <?php endforeach; ?>
            <?php if ($c['warnings'] !== []): ?>
              <details class="warns">
                <summary><?= count($c['warnings']) ?> ta eslatma</summary>
                <ul class="why"><?php foreach ($c['warnings'] as $w): ?><li><?= app_h($w) ?></li><?php endforeach; ?></ul>
              </details>
            <?php endif; ?>
          </td>
          <td class="nowrap"><?= app_h($regionOf((string) $p['region'])) ?></td>
          <td class="nowrap"><?= app_h((string) ($p['investmentDisplay'] ?? '—')) ?></td>
          <td class="num"><?= $pictures > 0 ? (int) $pictures : '—' ?></td>
          <td class="nowrap">
            <?= ['new' => 'yangi', 'ask' => 'tekshiring', 'duplicate' => 'saytda bor'][$c['state']] ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <?php if (isset($report['photos']) && $report['photos']['orphans'] !== []): ?>
      <p class="warn big">
        Bu rasmlar hech qaysi qatorga tegishli emas (kod mos kelmadi):
        <?= app_h(implode(', ', array_slice($report['photos']['orphans'], 0, 12))) ?><?= count($report['photos']['orphans']) > 12 ? '…' : '' ?>
      </p>
    <?php endif; ?>
    <?php if (isset($report['photos']) && $report['photos']['ignored'] !== []): ?>
      <p class="muted">
        Eʼtiborga olinmagan fayllar (nomi <code>KOD_1.jpg</code> koʻrinishida emas):
        <?= app_h(implode(', ', array_slice($report['photos']['ignored'], 0, 8))) ?><?= count($report['photos']['ignored']) > 8 ? '…' : '' ?>
      </p>
    <?php endif; ?>

    <div class="actions">
      <button type="submit">Belgilanganlarni saytga qoʻshish</button>
    </div>
  </form>
  <form method="post" class="inline">
    <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
    <input type="hidden" name="do" value="cancel">
    <input type="hidden" name="job" value="<?= app_h($job) ?>">
    <button class="btn btn--quiet" type="submit">Bekor qilish va faylni oʻchirish</button>
  </form>

<?php elseif ($page === 'manual'): /* ------------------------- one by hand --- */ ?>
  <nav class="crumbs"><a href="import.php">Loyiha qoʻshish</a> › Bitta loyiha</nav>
  <form method="post" class="editor">
    <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
    <input type="hidden" name="do" value="manual">
    <p class="muted lede">
      Yulduzcha bilan belgilangan katakchalar toʻldirilishi shart. Qolganlari
      boʻsh qolsa, loyiha baribir qoʻshiladi — toʻldirilmagan qatorlar saytda
      koʻrinmaydi, lekin keyin toʻldirish mumkin.
    </p>
    <?php foreach (import_columns() as $column):
        $id = 'f_' . $column['key'];
        $value = (string) ($_POST[$id] ?? '');
        $long = in_array($column['key'], ['description', 'why'], true);
    ?>
      <div class="fld<?= !empty($column['internal']) ? ' fld--internal' : '' ?>">
        <label for="<?= app_h($id) ?>">
          <?= app_h($column['header']) ?><?= !empty($column['required']) ? ' <span class="req">*</span>' : '' ?>
        </label>
        <?php if (isset($column['list'])): ?>
          <select id="<?= app_h($id) ?>" name="<?= app_h($id) ?>">
            <option value="">—</option>
            <?php foreach ($column['list'] as $option): ?>
              <option value="<?= app_h($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= app_h($option) ?></option>
            <?php endforeach; ?>
          </select>
        <?php elseif ($long): ?>
          <textarea id="<?= app_h($id) ?>" name="<?= app_h($id) ?>" rows="4"><?= app_h($value) ?></textarea>
        <?php else: ?>
          <input id="<?= app_h($id) ?>" name="<?= app_h($id) ?>" type="text" value="<?= app_h($value) ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <label class="switch">
      <input type="checkbox" name="anyway" value="1">
      <span>Oʻxshash loyiha topilsa ham qoʻshilsin</span>
    </label>
    <div class="actions">
      <button type="submit">Qoʻshish va saytni yangilash</button>
      <a class="btn btn--quiet" href="import.php">Bekor qilish</a>
    </div>
  </form>

<?php else: /* ------------------------------------------------- the start --- */ ?>
  <section class="cards cards--lead">
    <div class="card card--form">
      <b>Roʻyxatdan qoʻshish</b>
      <span>Toʻldirilgan .xlsx yoki .csv, rasmlar esa alohida .zip da.</span>
      <form method="post" enctype="multipart/form-data" class="upload" data-limit="<?= (int) $limit ?>">
        <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
        <input type="hidden" name="do" value="upload">
        <label for="list">Loyihalar roʻyxati</label>
        <input id="list" name="list" type="file" accept=".xlsx,.csv,.CSV,.XLSX" required>
        <label for="photos">Rasmlar arxivi — ixtiyoriy</label>
        <input id="photos" name="photos" type="file" accept=".zip,.ZIP">
        <small>
          Rasm fayllari loyiha kodi bilan nomlanadi: <code>SUR-01_1.jpg</code>.
          Bitta loyihaga <?= PHOTO_MAX_PER_PROJECT ?> tagacha rasm olinadi.
          Eng katta fayl — <?= app_h(photos_say_size($limit)) ?>.
        </small>
        <button type="submit">Yuklash va tekshirish</button>
      </form>
      <p class="muted small">
        <a href="import.php?action=template">Namuna faylni yuklab olish</a>
      </p>
      <p class="muted small">
        <button class="linkish" type="button" data-probe>Server fayl qabul qiladimi?</button>
        <span data-probe-out></span>
      </p>
    </div>
    <a class="card card--link" href="import.php?page=manual">
      <b>Bitta loyihani qoʻlda kiritish</b>
      <span>Fayl shart emas — ustunlarni shu yerda toʻldirasiz.</span>
    </a>
  </section>

  <?php if (!photos_can()): ?>
    <p class="warn big"><?= app_h(photos_complaint_server()) ?></p>
  <?php endif; ?>

  <h2 class="group-head">Adminkadan qoʻshilgan loyihalar</h2>
  <?php if ($ours === []): ?>
    <p class="empty">Hozircha bittasi ham qoʻshilmagan. Saytdagi <?= count($published) ?> ta loyiha build dan keladi.</p>
  <?php else: ?>
    <table class="rows">
      <thead><tr><th>Loyiha</th><th>Hudud</th><th>Investitsiya</th><th>Rasm</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($ours as $p): if (!is_array($p)) { continue; } ?>
        <tr>
          <td>
            <a href="site.php?f=forum&amp;page=project&amp;id=<?= app_h((string) $p['id']) ?>"><b><?= app_h((string) ($p['titleI18n']['uz'] ?? $p['id'])) ?></b></a>
            <div class="muted small"><?= app_h((string) $p['id']) ?></div>
          </td>
          <td class="nowrap"><?= app_h($regionOf((string) ($p['region'] ?? ''))) ?></td>
          <td class="nowrap"><?= app_h((string) ($p['investmentDisplay'] ?? '—')) ?></td>
          <td class="num"><?= count((array) ($p['photos'] ?? [])) ?></td>
          <td>
            <form method="post" class="inline" data-confirm="Loyiha saytdan butunlay olib tashlanadi. Davom etilsinmi?">
              <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
              <input type="hidden" name="do" value="remove">
              <input type="hidden" name="id" value="<?= app_h((string) $p['id']) ?>">
              <button class="btn btn--quiet" type="submit">Oʻchirish</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>
</main>
<?php
admin_foot();
