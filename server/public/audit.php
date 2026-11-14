<?php
/**
 * The log: what was done, by whom, and when.
 *
 * Read-only. There is no button here that changes a line and none that clears
 * the file — a log with a clear button says whatever the last person to press
 * it wanted it to say, which is not a log.
 *
 * Everyone who can open the admin can read it, including an editor reading
 * their own work: a record kept secretly from the people it is about is a
 * record they have no reason to trust. Exporting it takes personal data off
 * the server, so the export itself is written down like any other.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/adminui.php';

$config = app_config();
$user = admin_boot($config, 'audit.view');

$filter = [
    'month' => (string) ($_GET['month'] ?? ''),
    'login' => (string) ($_GET['who'] ?? ''),
    'act' => (string) ($_GET['act'] ?? ''),
    'q' => (string) ($_GET['q'] ?? ''),
];

if (($_GET['action'] ?? '') === 'csv') {
    $rows = audit_read($config, $filter, 5000);
    audit_log($config, 'audit.export', ['qatorlar' => count($rows)]);
    admin_csv_start('jurnal-' . gmdate('Y-m-d') . '.csv');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['vaqt', 'kim', 'huquq', 'manzil', 'amal', 'obyekt', 'tafsilot'], ';', '"', '');
    foreach ($rows as $row) {
        $detail = [];
        foreach ((array) ($row['detail'] ?? []) as $k => $v) {
            $detail[] = $k . ': ' . $v;
        }
        fputcsv($out, array_map('admin_csv_cell', [
            (string) ($row['t'] ?? ''),
            (string) ($row['login'] ?? ''),
            (string) ($row['role'] ?? ''),
            (string) ($row['ip'] ?? ''),
            AUDIT_LABELS[$row['act'] ?? ''] ?? (string) ($row['act'] ?? ''),
            (string) ($row['target'] ?? ''),
            implode(' · ', $detail),
        ]), ';', '"', '');
    }
    fclose($out);
    exit;
}

$rows = audit_read($config, $filter, 400);
$months = audit_months($config);
$actors = audit_actors($config);
$query = http_build_query(array_filter([
    'month' => $filter['month'], 'who' => $filter['login'],
    'act' => $filter['act'], 'q' => $filter['q'],
]));

admin_head($config, $user, 'Jurnal', 'audit');
?>
<main class="wide">
<?php admin_flash_print(); ?>

<p class="muted lede">
  Adminkada bajarilgan har bir oʻzgarish shu yerga yoziladi — kirish, chiqish,
  notoʻgʻri parol, sayt matnining tahriri, qoʻshilgan loyiha, yuklangan rasm,
  berilgan huquq. Jurnal oʻchirilmaydi va tahrirlanmaydi.
</p>

<form class="filters" method="get">
  <input type="search" name="q" value="<?= app_h($filter['q']) ?>" placeholder="Obyekt, tafsilot, IP…">
  <select name="who">
    <option value="">Hamma</option>
    <?php foreach ($actors as $who): ?>
      <option value="<?= app_h($who) ?>" <?= $filter['login'] === $who ? 'selected' : '' ?>><?= app_h($who) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="act">
    <option value="">Barcha amallar</option>
    <?php foreach (AUDIT_LABELS as $key => $label): ?>
      <option value="<?= app_h($key) ?>" <?= $filter['act'] === $key ? 'selected' : '' ?>><?= app_h($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="month">
    <option value="">Barcha oylar</option>
    <?php foreach ($months as $month): ?>
      <option value="<?= app_h($month) ?>" <?= $filter['month'] === $month ? 'selected' : '' ?>><?= app_h($month) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Qidirish</button>
  <a class="btn btn--quiet" href="audit.php">Tozalash</a>
  <a class="btn" href="audit.php?action=csv&amp;<?= app_h($query) ?>">CSV</a>
</form>

<p class="muted count"><?= count($rows) ?> ta yozuv koʻrsatilyapti · jurnalda jami <?= audit_total($config) ?> ta</p>

<?php if ($rows === []): ?>
  <p class="empty">Jurnal hozircha boʻsh.</p>
<?php else: ?>
<table class="rows regs">
  <thead>
    <tr><th>Vaqt</th><th>Kim</th><th>Amal</th><th>Nimaga</th><th>Tafsilot</th><th>Manzil</th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $row):
      $act = (string) ($row['act'] ?? '');
      $bad = str_contains($act, 'failed') || str_contains($act, 'locked') || str_contains($act, 'captcha');
  ?>
    <tr<?= $bad ? ' class="row--warn"' : '' ?>>
      <td class="nowrap" data-th="Vaqt"><?= app_h(str_replace('T', ' ', substr((string) ($row['t'] ?? ''), 0, 16))) ?></td>
      <td class="nowrap" data-th="Kim">
        <b><?= app_h((string) ($row['login'] ?? '—')) ?></b>
        <?php if (($row['role'] ?? '') !== ''): ?>
          <div class="muted small"><?= app_h(admin_role_word((string) $row['role'])) ?></div>
        <?php endif; ?>
      </td>
      <td data-th="Amal"><?= app_h(AUDIT_LABELS[$act] ?? $act) ?></td>
      <td class="small" data-th="Nimaga"><?= app_h((string) ($row['target'] ?? '')) ?></td>
      <td class="small" data-th="Tafsilot">
        <?php foreach ((array) ($row['detail'] ?? []) as $k => $v): ?>
          <span class="kv"><i><?= app_h((string) $k) ?></i> <?= app_h((string) $v) ?></span>
        <?php endforeach; ?>
      </td>
      <td class="nowrap muted small" data-th="Manzil"><?= app_h((string) ($row['ip'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</main>
<?php
admin_foot();
