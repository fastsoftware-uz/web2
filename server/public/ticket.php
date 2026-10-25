<?php
/**
 * The e-ticket, as a page.
 *
 *   /ticket/TITF26-000417-K7F3QA           the badge, A4, ready to print
 *   /ticket/TITF26-000417-K7F3QA?scan=1    what the QR code opens
 *
 * One number, two views. The badge is the sheet a guest prints and folds; the
 * scan view is what the person on the door sees when they point a telephone
 * at it — a name, a role and one word telling them whether to let the holder
 * in. That is why the QR carries a link rather than the number alone: a
 * forum's door staff have the telephones they already own, and no scanner.
 *
 * Both views are plain HTML with the styles inline. The page is going to be
 * saved, mailed, opened in a mail client's own browser and printed from a
 * telephone, and every one of those loses a stylesheet it has to fetch.
 *
 * It needs no login, by design: the ticket number is the credential, and it
 * is signed (see src/ticket.php). It shows nothing a badge does not show —
 * no e-mail address, no telephone number, no note.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/ticket.php';
require_once __DIR__ . '/../src/qr.php';
require_once __DIR__ . '/../src/site.php';

$config = app_config();

/* The number arrives either as a path segment (/ticket/NUMBER, which is what
   nginx and the dev router rewrite) or as ?t=, which is the form that
   survives being pasted into a mail. */
$number = (string) ($_GET['t'] ?? '');
if ($number === '') {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    if (preg_match('#/ticket/([A-Za-z0-9-]+)#', $path, $m) === 1) {
        $number = $m[1];
    }
}

$id = $number === '' ? null : ticket_id($number, $config);
$row = $id === null ? null : ticket_row($config, $id);
$scan = isset($_GET['scan']);

header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

/* ------------------------------------------------------------- the event */

/**
 * The dates and the city, from the site's own data file rather than from a
 * constant here: the organiser can move the forum from the admin, and a
 * badge printed afterwards that still says November would be worse than no
 * badge at all.
 */
function ticket_event(array $config): array
{
    $fallback = [
        'start' => '2026-11-25',
        'end' => '2026-11-27',
        'city_uz' => 'Toshkent',
        'city_en' => 'Tashkent',
        'name_uz' => 'I Xalqaro turizm investitsiya forumi',
        'name_en' => 'First International Tourism Investment Forum',
        'venue_uz' => '',
        'venue_en' => '',
        'organiser_uz' => 'Oʻzbekiston Respublikasi Turizm qoʻmitasi',
        'organiser_en' => 'Tourism Committee of the Republic of Uzbekistan',
    ];
    $file = site_dir($config) . '/data/forum-2026.json';
    if (!is_file($file)) {
        return $fallback;
    }
    $data = json_decode((string) file_get_contents($file), true);
    $event = $data['event'] ?? null;
    if (!is_array($event)) {
        return $fallback;
    }
    $contact = $data['contact'] ?? [];
    return [
        'start' => (string) ($event['dateStart'] ?? $fallback['start']),
        'end' => (string) ($event['dateEnd'] ?? $fallback['end']),
        'city_uz' => (string) ($event['city']['uz'] ?? $fallback['city_uz']),
        'city_en' => (string) ($event['city']['en'] ?? $fallback['city_en']),
        'name_uz' => (string) ($event['name']['uz'] ?? $fallback['name_uz']),
        'name_en' => (string) ($event['name']['en'] ?? $fallback['name_en']),
        'venue_uz' => (string) ($contact['address']['uz'] ?? ''),
        'venue_en' => (string) ($contact['address']['en'] ?? ''),
        'organiser_uz' => (string) ($contact['organisation']['uz'] ?? $fallback['organiser_uz']),
        'organiser_en' => (string) ($contact['organisation']['en'] ?? $fallback['organiser_en']),
    ];
}

/** "25–27 noyabr 2026" / "25–27 November 2026". */
function ticket_dates(array $event, string $lang): string
{
    $uz = ['', 'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];
    $en = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $start = date_create($event['start']);
    $end = date_create($event['end']);
    if (!$start || !$end) {
        return '';
    }
    $months = $lang === 'uz' ? $uz : $en;
    $month = $months[(int) $end->format('n')];
    $days = $start->format('j') === $end->format('j')
        ? $end->format('j')
        : $start->format('j') . '–' . $end->format('j');
    return $lang === 'uz'
        ? $days . ' ' . $month . ' ' . $end->format('Y')
        : $days . ' ' . $month . ' ' . $end->format('Y');
}

/** The forum's mark, inlined from the served site so it prints. */
function ticket_mark(array $config): string
{
    $file = site_dir($config) . '/brand/forum-lockup-mono-dark.svg';
    if (!is_file($file)) {
        $file = site_dir($config) . '/brand/forum-lockup.svg';
    }
    if (!is_file($file)) {
        return '';
    }
    $svg = (string) file_get_contents($file);
    // Two copies of one file on one page would share its gradient ids.
    static $seen = 0;
    $suffix = '-t' . (++$seen);
    $svg = (string) preg_replace('/id="([^"]+)"/', 'id="$1' . $suffix . '"', $svg);
    $svg = (string) preg_replace('/url\(#([^)]+)\)/', 'url(#$1' . $suffix . ')', $svg);
    return $svg;
}

$event = ticket_event($config);
$badge = $row === null ? null : ticket_badge($row, $config);
if ($badge === null) {
    http_response_code(404);
}

$h = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $badge ? 'E-bilet · ' . $h($badge['number']) : 'E-bilet' ?></title>
<style>
  :root {
    --navy: #1d3577;
    --ink: #15193a;
    --soft: #4a5070;
    --gold: #c18429;
    --line: #c9ccd8;
    --ok: #1d7a46;
    --no: #b3243b;
  }
  * { box-sizing: border-box; }
  html { -webkit-text-size-adjust: 100%; }
  body {
    margin: 0;
    background: #eef0f5;
    color: var(--ink);
    font: 400 16px/1.5 "Segoe UI", Roboto, system-ui, -apple-system, Arial, sans-serif;
  }

  /* ----------------------------------------------------- the scan view */
  .scan {
    max-width: 30rem;
    margin: 0 auto;
    padding: 1.25rem 1rem 3rem;
  }
  .scan__mark { width: 11rem; margin: 1.5rem auto 2rem; display: block; }
  .scan__mark svg { width: 100%; height: auto; }
  .card {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 1px 2px rgb(21 25 58 / 0.08), 0 8px 28px rgb(21 25 58 / 0.1);
    overflow: hidden;
  }
  .card__state {
    padding: 1.1rem 1.25rem;
    color: #fff;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.02em;
  }
  .card__state--ok { background: var(--ok); }
  .card__state--no { background: var(--no); }
  .card__state small { display: block; font-weight: 400; opacity: 0.9; font-size: 0.8rem; margin-top: 0.15rem; }
  .card__body { padding: 1.25rem; }
  .card__name { margin: 0 0 0.25rem; font-size: 1.5rem; line-height: 1.2; }
  .card__role {
    display: inline-block;
    margin: 0.5rem 0 1rem;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: #eef1f8;
    color: var(--navy);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.14em;
  }
  .card__rows { margin: 0; display: grid; gap: 0.75rem; }
  .card__rows div { display: grid; grid-template-columns: 7.5rem 1fr; gap: 0.75rem; }
  .card__rows dt { color: var(--soft); font-size: 0.8rem; }
  .card__rows dd { margin: 0; }
  .card__number { font-family: ui-monospace, Menlo, Consolas, monospace; letter-spacing: 0.04em; }
  .scan__print {
    display: block;
    margin: 1.25rem 0 0;
    padding: 0.9rem;
    border-radius: 0.6rem;
    background: var(--navy);
    color: #fff;
    text-align: center;
    text-decoration: none;
    font-weight: 600;
  }
  .scan__note { color: var(--soft); font-size: 0.85rem; margin-top: 1.25rem; }

  /* -------------------------------------------------------- the badge */
  .sheet {
    width: 210mm;
    min-height: 297mm;
    margin: 1.5rem auto;
    background: #fff;
    position: relative;
    box-shadow: 0 10px 40px rgb(21 25 58 / 0.18);
  }
  .quad {
    position: absolute;
    width: 50%;
    height: 50%;
    padding: 9mm 7mm;
    border: 1px dashed #b6bccb;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .quad--tl { top: 0; left: 0; }
  .quad--tr { top: 0; right: 0; }
  .quad--bl { bottom: 0; left: 0; }
  .quad--br { bottom: 0; right: 0; text-align: left; align-items: stretch; }

  .quad__mark { width: 52mm; margin-bottom: 2mm; }
  .quad__mark svg { width: 100%; height: auto; }
  .quad__forum { margin: 0 0 2mm; font-size: 8pt; line-height: 1.35; color: var(--soft); }
  .quad__seam {
    width: 26mm;
    height: 0.7mm;
    background: linear-gradient(to right, #e4bb7d, #c18429);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .quad__who { margin: 3mm 0 0; font-size: 12.5pt; line-height: 1.3; font-weight: 700; }
  .quad__who span { display: block; font-size: 9.5pt; font-weight: 400; color: var(--soft); margin-top: 1mm; }
  .quad__qr { width: 34mm; margin: 3mm auto 2mm; }
  .quad__qr svg { width: 100%; height: auto; display: block; }
  .quad__when {
    display: inline-block;
    margin: 0 0 2mm;
    padding: 1mm 2.5mm;
    background: var(--navy);
    color: #fff;
    font-size: 9pt;
    font-weight: 700;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .quad__role {
    margin: 1mm 0 0;
    font-size: 26pt;
    line-height: 1;
    font-weight: 800;
    letter-spacing: 0.02em;
    color: var(--navy);
  }
  .quad__free { margin: 2mm 0 0; font-size: 7.5pt; color: var(--soft); }
  .quad__no {
    margin: 2mm 0 0;
    font-size: 9pt;
    font-weight: 700;
    color: var(--no);
  }

  .quad__head {
    display: inline-block;
    margin: 0 0 3mm;
    padding: 1mm 2.5mm;
    background: var(--navy);
    color: #fff;
    font-size: 8.5pt;
    font-weight: 700;
    letter-spacing: 0.06em;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .quad__venue { margin: 0 0 2mm; font-size: 10pt; line-height: 1.45; }
  .quad__venue b { display: block; font-size: 11pt; }
  .quad__map { width: 44mm; margin: 2mm auto; }
  .quad__map svg { width: 100%; height: auto; display: block; }
  .quad__small { margin: 2mm 0 0; font-size: 7pt; line-height: 1.45; color: var(--soft); }
  .quad__rule { width: 100%; border: 0; border-top: 1px solid var(--line); margin: 3mm 0; }

  .steps { margin: 0; padding: 0; list-style: none; }
  .steps li { font-size: 8pt; line-height: 1.5; margin-bottom: 1.5mm; padding-left: 5mm; position: relative; }
  .steps li::before {
    content: "";
    position: absolute;
    left: 0;
    top: 1.4mm;
    width: 2mm;
    height: 2mm;
    background: var(--gold);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .steps--en li::before { background: var(--navy); }
  .quad__label { margin: 0 0 1.5mm; font-size: 8.5pt; font-weight: 700; color: var(--navy); }
  .fold { width: 100%; margin: 2mm 0 0; }
  .quad__number {
    margin: auto 0 0;
    padding-top: 2mm;
    font-size: 8pt;
    font-family: ui-monospace, Menlo, Consolas, monospace;
    letter-spacing: 0.04em;
    color: var(--soft);
    text-align: center;
  }

  .toolbar {
    max-width: 210mm;
    margin: 1.5rem auto -0.5rem;
    display: flex;
    gap: 0.75rem;
    align-items: center;
    padding: 0 1rem;
  }
  .toolbar button {
    font: inherit;
    font-weight: 600;
    padding: 0.7rem 1.4rem;
    border: 0;
    border-radius: 0.5rem;
    background: var(--navy);
    color: #fff;
    cursor: pointer;
  }
  .toolbar p { margin: 0; color: var(--soft); font-size: 0.875rem; }

  .missing { max-width: 30rem; margin: 4rem auto; padding: 0 1rem; text-align: center; }
  .missing h1 { font-size: 1.5rem; }
  .missing p { color: var(--soft); }

  @media (max-width: 230mm) {
    .sheet { zoom: 0.46; }
    .toolbar { flex-direction: column; align-items: flex-start; }
  }

  @page { size: A4; margin: 0; }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { width: 210mm; height: 297mm; margin: 0; box-shadow: none; zoom: 1; }
  }
</style>
</head>
<body>
<?php if ($badge === null): ?>
  <div class="missing">
    <h1>Bilet topilmadi</h1>
    <p>
      Bu havola notoʻgʻri yoki bilet bekor qilingan.<br>
      <em>This ticket number is not valid.</em>
    </p>
    <p>
      <?= $h($event['organiser_uz']) ?><br>
      <?= $h((string) ($config['site_name'] ?? '')) ?>
    </p>
  </div>
<?php elseif ($scan): ?>
  <?php /* What the door sees. One word, in a colour you can read across a desk. */ ?>
  <div class="scan">
    <div class="scan__mark"><?= ticket_mark($config) ?></div>
    <div class="card">
      <div class="card__state <?= $badge['valid'] ? 'card__state--ok' : 'card__state--no' ?>">
        <?= $badge['valid'] ? 'Bilet haqiqiy · Valid ticket' : 'Bilet bekor qilingan · Cancelled' ?>
        <small>
          <?php if ($badge['status'] === 'confirmed'): ?>
            Tashkiliy qoʻmita tasdiqlagan · Confirmed by the organisers
          <?php elseif ($badge['status'] === 'declined'): ?>
            Kirishga ruxsat berilmaydi · Do not admit
          <?php else: ?>
            Roʻyxatdan oʻtgan, tasdiq kutilmoqda · Registered, awaiting confirmation
          <?php endif; ?>
        </small>
      </div>
      <div class="card__body">
        <h1 class="card__name"><?= $h($badge['name']) ?></h1>
        <p class="card__role"><?= $h($badge['role_uz']) ?> · <?= $h($badge['role_en']) ?></p>
        <dl class="card__rows">
          <?php if ($badge['position'] !== ''): ?>
            <div><dt>Lavozim</dt><dd><?= $h($badge['position']) ?></dd></div>
          <?php endif; ?>
          <?php if ($badge['organisation'] !== ''): ?>
            <div><dt>Tashkilot</dt><dd><?= $h($badge['organisation']) ?></dd></div>
          <?php endif; ?>
          <?php if ($badge['country'] !== ''): ?>
            <div><dt>Davlat</dt><dd><?= $h($badge['country']) ?></dd></div>
          <?php endif; ?>
          <div><dt>Bilet raqami</dt><dd class="card__number"><?= $h($badge['number']) ?></dd></div>
          <div><dt>Sana</dt><dd><?= $h(ticket_dates($event, 'uz')) ?>, <?= $h($event['city_uz']) ?></dd></div>
        </dl>
      </div>
    </div>
    <a class="scan__print" href="<?= $h('/ticket/' . $badge['number']) ?>">Beyjni chop etish · Print the badge</a>
    <p class="scan__note">
      <?= $h($event['organiser_uz']) ?><br>
      <?= $h($event['name_uz']) ?>
    </p>
  </div>
<?php else: ?>
  <div class="toolbar">
    <button type="button" onclick="window.print()">Chop etish · Print</button>
    <p>A4, rangli yoki oq-qora. Chop etgach, punktir chiziqlar boʻyicha buklang.</p>
  </div>

  <div class="sheet">
    <?php
    $qr = qr_svg(ticket_url($badge['id'], $config, true), '', '#15193a');
    $faces = [
        [
            'forum' => $event['name_uz'],
            'when' => ticket_dates($event, 'uz') . ', ' . $event['city_uz'],
            'role' => $badge['role_uz'],
            'free' => 'Ushbu beyj forumga kirish huquqini beradi',
            'void' => 'BEKOR QILINGAN — kirishga yaramaydi',
        ],
        [
            'forum' => $event['name_en'],
            'when' => ticket_dates($event, 'en') . ', ' . $event['city_en'],
            'role' => $badge['role_en'],
            'free' => 'This badge is your pass to the forum',
            'void' => 'CANCELLED — not valid for entry',
        ],
    ];
    ?>
    <?php foreach ($faces as $i => $face): ?>
      <div class="quad quad--<?= $i === 0 ? 'tl' : 'tr' ?>">
        <div class="quad__mark"><?= ticket_mark($config) ?></div>
        <p class="quad__forum"><?= $h($face['forum']) ?></p>
        <div class="quad__seam" aria-hidden="true"></div>
        <p class="quad__who">
          <?= $h($badge['name']) ?>
          <span>
            <?= $h($badge['position']) ?><?= $badge['position'] !== '' && $badge['organisation'] !== '' ? ' · ' : '' ?><?= $h($badge['organisation']) ?>
            <?php if ($badge['country'] !== ''): ?><br><?= $h($badge['country']) ?><?php endif; ?>
          </span>
        </p>
        <div class="quad__qr"><?= $qr ?></div>
        <p class="quad__when"><?= $h($face['when']) ?></p>
        <p class="quad__role"><?= $h($face['role']) ?></p>
        <?php if ($badge['valid']): ?>
          <p class="quad__free"><?= $h($face['free']) ?></p>
        <?php else: ?>
          <p class="quad__no"><?= $h($face['void']) ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="quad quad--bl">
      <p class="quad__head">FORUM · <?= $h(ticket_dates($event, 'uz')) ?></p>
      <p class="quad__venue">
        <b><?= $h($event['city_uz']) ?> · <?= $h($event['city_en']) ?></b>
        <?= $h($event['name_uz']) ?>
      </p>
      <hr class="quad__rule">
      <?php /* The address is the organising committee's, and says so: the
               hall itself is not in the data file, and a badge that names a
               venue nobody confirmed is worse than a badge that names none. */ ?>
      <p class="quad__small">TASHKILIY QOʻMITA · ORGANISING COMMITTEE</p>
      <p class="quad__venue">
        <?= $h($event['organiser_uz']) ?><br>
        <?= $h($event['venue_uz']) ?>
      </p>
      <div class="quad__map"><?= qr_svg(
          'https://www.openstreetmap.org/search?query=' . rawurlencode(
              ($event['venue_en'] !== '' ? $event['venue_en'] : $event['venue_uz']) . ', ' . $event['city_en']
          ),
          '',
          '#1d3577'
      ) ?></div>
      <p class="quad__small">Xaritada ochish · Open on a map</p>
      <hr class="quad__rule">
      <p class="quad__small">
        Beyjimni skanerlashga ruxsat berar ekanman, ismim va tashkilotim tashkiliy qoʻmitaga
        koʻrinishini bilaman. Elektron pochtam va telefon raqamim beyjda yozilmagan va
        skanerlanganda ham koʻrinmaydi.
      </p>
      <p class="quad__small">
        By allowing your badge to be scanned you agree that your name and organisation are
        shown to the organisers. Your e-mail address and telephone number are not on the badge
        and are not revealed by scanning it.
      </p>
    </div>

    <div class="quad quad--br">
      <p class="quad__label">YOʻRIQNOMA</p>
      <ul class="steps">
        <li>Varaqni A4 qogʻozda chop eting.</li>
        <li>Punktir chiziqlar boʻyicha buklang — ikki yuzida ham ismingiz boʻladi.</li>
        <li>QR kod ochiq qolsin; ustiga yozmang va buklamang.</li>
        <li>Forumga kirishda beyjni koʻrsating. Tashkilotchi shaxsni tasdiqlovchi hujjat soʻrashi mumkin.</li>
        <li>Ekrandagi nusxa ham oʻqiladi, lekin chop etilgani ishonchliroq.</li>
      </ul>
      <p class="quad__label" style="margin-top:3mm">INSTRUCTIONS</p>
      <ul class="steps steps--en">
        <li>Print this sheet on A4 paper.</li>
        <li>Fold along the dashed lines — your name is then on both sides.</li>
        <li>Keep the QR code clear: do not write on it or fold it.</li>
        <li>Show the badge at the entrance. The organisers may ask for identification.</li>
        <li>A copy on a screen can be read, but a printed one is more reliable.</li>
      </ul>

      <svg class="fold" viewBox="0 0 120 46" role="img" aria-label="Buklash tartibi">
        <g fill="none" stroke="#4a5070" stroke-width="0.8">
          <rect x="1" y="1" width="36" height="44" />
          <line x1="19" y1="1" x2="19" y2="45" stroke-dasharray="2 2" />
          <line x1="1" y1="23" x2="37" y2="23" stroke-dasharray="2 2" />
          <path d="M45 23h8m-2-2 2 2-2 2" />
          <rect x="60" y="1" width="18" height="44" />
          <line x1="60" y1="23" x2="78" y2="23" stroke-dasharray="2 2" />
          <path d="M86 23h8m-2-2 2 2-2 2" />
          <rect x="101" y="1" width="18" height="22" />
        </g>
      </svg>

      <p class="quad__number">
        <?= $h($badge['number']) ?>
        <?php if (!$badge['valid']): ?> · BEKOR QILINGAN<?php endif; ?>
      </p>
    </div>
  </div>
<?php endif; ?>
</body>
</html>
