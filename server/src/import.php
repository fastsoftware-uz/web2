<?php
/**
 * Reading a filled-in project list, and refusing it when it is wrong.
 *
 * This is the server-side twin of scripts/import-intake.mjs. The repository's
 * own pipeline reads the same spreadsheet with Node, ExcelJS and sharp before
 * a build; the admin has to read it on the organiser's server, where none of
 * those exist. Both take the same file and have to agree about it, so the
 * table below is the same table, the messages are the same messages, and a
 * row that the build would refuse is refused here in the same words.
 *
 * ## What it adds that the build does not
 *
 * The build runs against a folder of submissions and then rebuilds the
 * portfolio from scratch, so a project appearing twice is a thing the whole
 * pipeline can be made to notice. The admin adds to a portfolio that is
 * already published, from a file somebody is holding, in a room, now — and
 * the thing that goes wrong is that the list is largely projects that are
 * already on the site. That happened on 13 November 2026: of nine rows sent
 * in, seven were already published, two of them under different names.
 *
 * So every candidate is compared against the live portfolio before anything
 * is written, on four tests, and the ones that are not certain are not
 * silently dropped either — they are shown to the person at the keyboard with
 * the project they look like, and nothing is added until they say which.
 *
 * Nothing here writes. It reads a file and produces a report; public/import.php
 * shows the report and, on a second request, writes what the report approved.
 */

declare(strict_types=1);

require_once __DIR__ . '/xlsx.php';
require_once __DIR__ . '/site.php';
require_once __DIR__ . '/photos.php';

/* ------------------------------------------------------------- vocabularies */

/** Region code -> the portfolio's region key and the sheet's own wording. */
const INTAKE_REGIONS = [
    'AND' => ['region' => 'andijan', 'uz' => 'Andijon viloyati'],
    'BUX' => ['region' => 'bukhara', 'uz' => 'Buxoro viloyati'],
    'FAR' => ['region' => 'fergana', 'uz' => 'Fargʻona viloyati'],
    'JIZ' => ['region' => 'jizzakh', 'uz' => 'Jizzax viloyati'],
    'QQR' => ['region' => 'karakalpakstan', 'uz' => 'Qoraqalpogʻiston Respublikasi'],
    'QAS' => ['region' => 'kashkadarya', 'uz' => 'Qashqadaryo viloyati'],
    'NAM' => ['region' => 'namangan', 'uz' => 'Namangan viloyati'],
    'NAV' => ['region' => 'navoi', 'uz' => 'Navoiy viloyati'],
    'SAM' => ['region' => 'samarkand', 'uz' => 'Samarqand viloyati'],
    'SIR' => ['region' => 'syrdarya', 'uz' => 'Sirdaryo viloyati'],
    'SUR' => ['region' => 'surkhandarya', 'uz' => 'Surxondaryo viloyati'],
    'TSH' => ['region' => 'tashkent_city', 'uz' => 'Toshkent shahri'],
    'TOV' => ['region' => 'tashkent_region', 'uz' => 'Toshkent viloyati'],
    'XOR' => ['region' => 'khorezm', 'uz' => 'Xorazm viloyati'],
];

const INTAKE_SEGMENTS = [
    'Mehmonxona va infratuzilma' => 'infrastructure',
    'Ekoturizm' => 'eco',
    'Madaniy meros' => 'heritage',
    'Hordiq va koʻngilochar' => 'leisure',
    'Salomatlik va wellness' => 'wellness',
    'Transport va logistika' => 'transport',
];

const INTAKE_STAGES = [
    'Yangi qurilish (greenfield)' => 'Greenfield',
    'Mavjud obyekt negizida (brownfield)' => 'Brownfield',
    'Konsept loyiha' => 'Concept',
    'Loyihalash bosqichi' => 'Design',
    'Qurilish jarayonida' => 'Construction',
    'Faoliyatdagi obyekt' => 'Operating',
    'Loyiha taklifi' => 'Project proposal',
];

const INTAKE_MONEY_NOTES = [
    'Summa aniq' => null,
    'Kelishuv asosida' => 'By agreement',
    'Aniqlanmoqda' => 'TBD',
];

const INTAKE_UTILITIES = [
    'Bor' => 'Available',
    'Qisman' => 'Partial',
    'Yoʻq' => 'Not available',
];

const INTAKE_CURRENCIES = ['USD', 'EUR', 'UZS'];

/**
 * The columns, in the order the template writes them.
 *
 * `label` and `group` are what turn a cell into a line in the published
 * panel. `internal` marks the three columns that are collected and never
 * published — the responsible organisation, its official line and the source
 * document. Nothing here asks for a person, and the personal-data test below
 * refuses one in any published column.
 */
function import_columns(): array
{
    return [
        ['key' => 'code', 'header' => 'KOD', 'required' => true],
        ['key' => 'region', 'header' => 'VILOYAT', 'required' => true, 'list' => array_column(INTAKE_REGIONS, 'uz')],
        ['key' => 'district', 'header' => 'TUMAN / SHAHAR', 'required' => true],
        ['key' => 'title_uz', 'header' => 'LOYIHA NOMI (UZ)', 'required' => true],
        ['key' => 'title_ru', 'header' => 'LOYIHA NOMI (RU)'],
        ['key' => 'title_en', 'header' => 'LOYIHA NOMI (EN)'],
        ['key' => 'segment', 'header' => 'YOʻNALISH', 'required' => true, 'list' => array_keys(INTAKE_SEGMENTS)],
        ['key' => 'activity', 'header' => 'FAOLIYAT TURI', 'required' => true, 'label' => 'ACTIVITY', 'group' => 'metrics'],
        ['key' => 'stage', 'header' => 'LOYIHA BOSQICHI', 'required' => true, 'list' => array_keys(INTAKE_STAGES), 'label' => 'STATUS', 'group' => 'metrics'],
        ['key' => 'address', 'header' => 'MANZIL', 'required' => true, 'label' => 'LOCATION', 'group' => 'metrics'],
        ['key' => 'coords', 'header' => 'KOORDINATA'],
        ['key' => 'amount', 'header' => 'INVESTITSIYA SUMMASI'],
        ['key' => 'currency', 'header' => 'VALYUTA', 'list' => INTAKE_CURRENCIES],
        ['key' => 'amount_note', 'header' => 'SUMMA HOLATI', 'required' => true, 'list' => array_keys(INTAKE_MONEY_NOTES)],
        ['key' => 'land', 'header' => 'YER MAYDONI, ga', 'label' => 'LAND AREA', 'group' => 'metrics'],
        ['key' => 'floor_area', 'header' => 'BINO MAYDONI, m²', 'label' => 'BUILDING AREA', 'group' => 'metrics'],
        ['key' => 'capacity', 'header' => 'QUVVAT', 'label' => 'CAPACITY', 'group' => 'metrics'],
        ['key' => 'jobs', 'header' => 'ISH OʻRINLARI', 'label' => 'JOBS', 'group' => 'metrics'],
        ['key' => 'payback', 'header' => 'QAYTIM MUDDATI', 'label' => 'PAYBACK', 'group' => 'opportunity'],
        ['key' => 'period', 'header' => 'QURILISH MUDDATI', 'label' => 'IMPLEMENTATION PERIOD', 'group' => 'opportunity'],
        ['key' => 'utilities', 'header' => 'KOMMUNIKATSIYA', 'list' => array_keys(INTAKE_UTILITIES), 'label' => 'UTILITIES', 'group' => 'concept'],
        ['key' => 'access', 'header' => 'AEROPORT / TEMIR YOʻL, km', 'label' => 'ACCESS', 'group' => 'concept'],
        ['key' => 'holder', 'header' => 'YER EGASI (TASHKILOT)', 'label' => 'BALANCE SHEET HOLDER', 'group' => 'opportunity'],
        ['key' => 'description', 'header' => 'LOYIHA TAVSIFI', 'required' => true],
        ['key' => 'why', 'header' => 'NEGA INVESTORGA QIZIQARLI', 'label' => 'MARKET POTENTIAL', 'group' => 'whyInvest'],
        ['key' => 'photos', 'header' => 'RASMLAR SONI'],
        ['key' => 'owner_org', 'header' => 'MASʼUL TASHKILOT (saytga chiqmaydi)', 'required' => true, 'internal' => true],
        ['key' => 'owner_contact', 'header' => 'RASMIY ALOQA (saytga chiqmaydi)', 'required' => true, 'internal' => true],
        ['key' => 'source', 'header' => 'MANBA HUJJAT (saytga chiqmaydi)', 'internal' => true],
        ['key' => 'note', 'header' => 'IZOH (saytga chiqmaydi)', 'internal' => true],
    ];
}

/** Cells that must never carry a person. */
function import_personal(): array
{
    return [
        ['re' => '/(\+?998[\s-]?\(?\d{2}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2})/', 'what' => 'telefon raqami'],
        ['re' => '/\b(?:STIR|ИНН|INN)\b[\s:№-]*\d{6,}/iu', 'what' => 'STIR'],
        ['re' => '/\b\d{20}\b/', 'what' => 'hisob raqami'],
    ];
}

/* ---------------------------------------------------------------- helpers */

/**
 * One string, reduced to what two strings have to share to be the same name.
 *
 * Case, the four apostrophes Uzbek Latin is written with, the quotation marks
 * the portfolio puts around a hotel's name, and every run of punctuation.
 * «Samarkand» mehmonxonasi and Samarkand mehmonxonasi are then one string,
 * which is the whole point: a list sent back by a committee rewrites a title
 * every time, and a duplicate test that compares them literally finds nothing.
 */
function import_fold(string $value): string
{
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = str_replace(
        ['ʻ', 'ʼ', '`', '‘', '’', '«', '»', '"', '“', '”', 'ʹ', "'"],
        ['', '', '', '', '', '', '', '', '', '', '', ''],
        $value,
    );
    $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

/** A header as written -> the key it names. `*`, case and spacing ignored. */
function import_header_key(string $header): string
{
    $want = import_fold(str_replace('*', '', $header));
    foreach (import_columns() as $column) {
        if (import_fold($column['header']) === $want) {
            return $column['key'];
        }
    }
    return '';
}

/** "1 234,5" -> 1234.5; an empty cell -> null; nonsense -> false. */
function import_number(string $raw): float|null|false
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $clean = str_replace([' ', ' ', ' '], '', $raw);
    $clean = str_replace(',', '.', $clean);
    if (!is_numeric($clean)) {
        return false;
    }
    return (float) $clean;
}

/** 2500000 -> "2.5M", the way the sheets write a sum. */
function import_say_money(float $n): string
{
    $trim = static fn(float $v, int $places): string
        => rtrim(rtrim(number_format($v, $places, '.', ''), '0'), '.');
    if ($n >= 1e9) {
        return $trim($n / 1e9, 2) . ' bn';
    }
    if ($n >= 1e6) {
        return $trim($n / 1e6, 2) . 'M';
    }
    if ($n >= 1e3) {
        return $trim($n / 1e3, 1) . 'K';
    }
    return $trim($n, 0);
}

/** "32 xona" -> "32 rooms". Anything unrecognised is passed through. */
function import_unit(string $value): string
{
    $words = [
        '/^([\d\s.,–—-]+)\s*xona$/iu' => 'rooms',
        '/^([\d\s.,–—-]+)\s*(?:oʻrin|o\'rin|orin)$/iu' => 'beds',
        '/^([\d\s.,–—-]+)\s*kishi\s*\/\s*kun$/iu' => 'people/day',
        '/^([\d\s.,–—-]+)\s*kishi\s*\/\s*soat$/iu' => 'people/hour',
        '/^([\d\s.,–—-]+)\s*kishi$/iu' => 'people',
        '/^([\d\s.,–—-]+)\s*tashrif\s*\/\s*yil$/iu' => 'visits/year',
        '/^([\d\s.,–—-]+)\s*yil$/iu' => 'years',
        '/^([\d\s.,–—-]+)\s*oy$/iu' => 'months',
        '/^([\d\s.,–—-]+)\s*km$/iu' => 'km',
        '/^([\d\s.,–—-]+)\s*ga$/iu' => 'ha',
    ];
    $value = trim($value);
    foreach ($words as $re => $unit) {
        if (preg_match($re, $value, $m) === 1) {
            return trim($m[1]) . ' ' . $unit;
        }
    }
    return $value;
}

/* ------------------------------------------------------------ reading a file */

/**
 * The sheet that holds the list, and where its header row is.
 *
 * The template writes the headers into row 1 of a sheet called LOYIHALAR, but
 * a file that has been through three offices may have a title row above them
 * or a second sheet in front. So the header row is looked for rather than
 * assumed: the first row of the first sheet that names at least six of the
 * columns is it.
 *
 * @return array{sheet:string,header:int,map:array<string,string>,rows:array<int,array<string,string>>}
 */
function import_open(string $file): array
{
    $sheets = [];
    if (import_looks_like_csv($file)) {
        $sheets = [['name' => 'CSV', 'path' => '', 'rows' => import_csv_rows($file)]];
    } else {
        $complaint = xlsx_complaint($file);
        if ($complaint !== '') {
            throw new RuntimeException($complaint);
        }
        $strings = xlsx_strings($file);
        foreach (xlsx_sheets($file) as $sheet) {
            $sheets[] = $sheet + ['rows' => null, 'strings' => true];
        }
    }
    $best = null;

    foreach ($sheets as $sheet) {
        $rows = $sheet['rows'] ?? xlsx_rows($file, $sheet['path'], $strings);
        foreach ($rows as $number => $cells) {
            if ($number > 12) {
                break;
            }
            $map = [];
            foreach ($cells as $column => $text) {
                $key = import_header_key($text);
                if ($key !== '' && !in_array($key, $map, true)) {
                    $map[$column] = $key;
                }
            }
            if (count($map) >= 6 && in_array('code', $map, true) && in_array('title_uz', $map, true)) {
                $best = ['sheet' => $sheet['name'], 'header' => $number, 'map' => $map, 'rows' => $rows];
                break 2;
            }
        }
    }

    if ($best === null) {
        throw new RuntimeException(
            'Faylda loyihalar jadvali topilmadi. Sarlavha qatorida kamida «KOD» va '
            . '«LOYIHA NOMI (UZ)» ustunlari boʻlishi kerak — adminkadagi namunani yuklab oling.',
        );
    }
    return $best;
}

/**
 * A .csv, rather than a workbook?
 *
 * Accepted because it is what somebody sends when the list was assembled in
 * Google Sheets, or saved out of Excel by a colleague who picked the first
 * entry in the Save-as menu. Refusing it would be refusing the same data for
 * being in a plainer wrapper.
 */
function import_looks_like_csv(string $file): bool
{
    $head = (string) @file_get_contents($file, false, null, 0, 8);
    if ($head === '') {
        return false;
    }
    /* A zip (xlsx) and an OLE compound file (.xls) are not CSV. */
    return strncmp($head, 'PK', 2) !== 0 && strncmp($head, "\xD0\xCF\x11\xE0", 4) !== 0;
}

/**
 * A CSV as the same row-and-column shape a sheet has.
 *
 * The separator is whichever of `;` and `,` the first line holds more of —
 * Excel writes the semicolon in this part of the world and the comma in
 * others, and a file split on the wrong one arrives as one enormous column.
 *
 * @return array<int,array<string,string>>
 */
function import_csv_rows(string $file): array
{
    $raw = (string) @file_get_contents($file, false, null, 0, 4194304);
    if ($raw === '') {
        throw new RuntimeException('Fayl boʻsh.');
    }
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    if (preg_match('//u', $raw) !== 1) {
        /* Not UTF-8: Excel's own "CSV" is the local codepage, and Uzbek
           Latin written in it arrives as mojibake nobody can match against a
           column name. Said plainly rather than parsed into nonsense. */
        throw new RuntimeException(
            'CSV fayl UTF-8 emas. Excelda «CSV UTF-8» koʻrinishida saqlang yoki .xlsx yuboring.',
        );
    }

    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $first = $lines[0] ?? '';
    $separator = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';

    $rows = [];
    $number = 0;
    foreach ($lines as $line) {
        $number++;
        if ($number > XLSX_MAX_ROWS) {
            break;
        }
        if (trim($line) === '') {
            continue;
        }
        $cells = str_getcsv($line, $separator, '"', '');
        $out = [];
        foreach ($cells as $i => $value) {
            if ($i >= XLSX_MAX_COLS) {
                break;
            }
            $value = xlsx_text((string) $value);
            if ($value !== '') {
                $out[import_col_letter($i)] = $value;
            }
        }
        if ($out !== []) {
            $rows[$number] = $out;
        }
    }
    return $rows;
}

/** 0 -> "A", 26 -> "AA": a column's spreadsheet name. */
function import_col_letter(int $i): string
{
    $name = '';
    $i++;
    while ($i > 0) {
        $i--;
        $name = chr(65 + ($i % 26)) . $name;
        $i = intdiv($i, 26);
    }
    return $name;
}

/**
 * Which columns the template has that this file has not.
 *
 * @return array<int,string> the headers that are missing
 */
function import_missing_columns(array $map): array
{
    $have = array_values($map);
    $missing = [];
    foreach (import_columns() as $column) {
        if (!empty($column['required']) && !in_array($column['key'], $have, true)) {
            $missing[] = $column['header'];
        }
    }
    return $missing;
}

/* ------------------------------------------------------------- checking a row */

const IMPORT_EMPTY_WORDS = '/^(-+|—+|yoʻq|yo\'q|yok|нет|none|n\/?a|aniqlanmagan|\.+|0{2,})$/iu';

/**
 * One row, checked.
 *
 * The line this draws is the one the build draws: a *wrong* cell refuses the
 * row, because a wrong figure on a public page cannot be seen to be wrong; a
 * *missing* cell is a warning and is carried on the project as something
 * still to be asked for, because an absent line is visible and chaseable.
 *
 * @return array{ok:bool,row:array<string,mixed>,errors:string[],warnings:string[]}
 */
function import_check_row(array $values, int $excelRow, array &$seen): array
{
    $errors = [];
    $warnings = [];
    $bad = static function (string $m) use (&$errors): void {
        $errors[] = $m;
    };
    $warn = static function (string $m) use (&$warnings): void {
        $warnings[] = $m;
    };
    $columns = [];
    foreach (import_columns() as $column) {
        $columns[$column['key']] = $column;
    }

    /* A word standing in for an empty cell is cleared, unless it is one of
       that column's own answers — "Yoʻq" under KOMMUNIKATSIYA is the answer
       "not connected", a fact an investor needs. */
    foreach ($values as $key => $value) {
        $column = $columns[$key] ?? null;
        if ($column !== null && in_array($value, $column['list'] ?? [], true)) {
            continue;
        }
        if ($value !== '' && preg_match(IMPORT_EMPTY_WORDS, $value) === 1) {
            $values[$key] = '';
            $warn('«' . ($column['header'] ?? $key) . '» ustunida «' . $value . '» yozilgan — boʻsh deb olindi');
        }
    }

    /* An example row left in the template would otherwise be published. */
    if (preg_match('/^namuna qator/iu', $values['note'] ?? '') === 1) {
        return ['ok' => false, 'row' => [], 'errors' => [], 'warnings' => [], 'skipped' => true];
    }

    $gaps = [];
    foreach ($columns as $key => $column) {
        if (!empty($column['required']) && trim((string) ($values[$key] ?? '')) === '') {
            $gaps[] = $column['header'];
            $warn('«' . $column['header'] . '» toʻldirilmagan');
        }
    }

    /* No person, anywhere. */
    foreach ($columns as $key => $column) {
        $value = (string) ($values[$key] ?? '');
        if ($value === '') {
            continue;
        }
        foreach (import_personal() as $test) {
            if (preg_match($test['re'], $value) !== 1) {
                continue;
            }
            if (!empty($column['internal']) && $key === 'owner_contact') {
                if (preg_match('/\+?998[\s-]?\(?9\d/', $value) === 1) {
                    $warn('«' . $column['header'] . '» shaxsiy mobil raqamga oʻxshaydi');
                }
            } elseif (!empty($column['internal'])) {
                $warn('«' . $column['header'] . '» ichida ' . $test['what']
                    . ' bor — saytga chiqmaydi, lekin olib tashlash tavsiya etiladi');
            } else {
                $bad('«' . $column['header'] . '» ichida ' . $test['what']
                    . ' bor — shaxsiy maʼlumot eʼlon qilinmaydi, olib tashlang');
            }
        }
    }

    /* The code, and the region it has to agree with. */
    $code = strtoupper(trim((string) ($values['code'] ?? '')));
    if (preg_match('/^([A-Z]{3})-(\d{2,3})$/', $code, $m) !== 1) {
        $bad('KOD «' . $code . '» — format BUX-01 koʻrinishida boʻlishi kerak');
        return ['ok' => false, 'row' => [], 'errors' => $errors, 'warnings' => $warnings];
    }
    $prefix = $m[1];
    $known = INTAKE_REGIONS[$prefix] ?? null;
    if ($known === null) {
        $bad('KOD dagi «' . $prefix . '» viloyat kodi roʻyxatda yoʻq');
        return ['ok' => false, 'row' => [], 'errors' => $errors, 'warnings' => $warnings];
    }
    if (isset($seen[$code])) {
        $bad('KOD ' . $code . ' shu faylda ikki marta uchraydi (' . $seen[$code] . '-qator bilan)');
        return ['ok' => false, 'row' => [], 'errors' => $errors, 'warnings' => $warnings];
    }
    $seen[$code] = $excelRow;

    $regionName = trim((string) ($values['region'] ?? ''));
    $regionCode = '';
    foreach (INTAKE_REGIONS as $c => $r) {
        if (import_fold($r['uz']) === import_fold($regionName)) {
            $regionCode = $c;
        }
    }
    if ($regionCode === '') {
        $bad('VILOYAT «' . $regionName . '» roʻyxatda yoʻq');
    } elseif ($regionCode !== $prefix) {
        $bad('KOD ' . $code . ' ' . $known['uz'] . ' ga tegishli, VILOYAT ustunida esa ' . $regionName);
    }

    /* The closed columns. */
    $segment = INTAKE_SEGMENTS[$values['segment'] ?? ''] ?? '';
    if (($values['segment'] ?? '') !== '' && $segment === '') {
        $bad('YOʻNALISH «' . $values['segment'] . '» roʻyxatda yoʻq');
    }
    $stage = INTAKE_STAGES[$values['stage'] ?? ''] ?? '';
    if (($values['stage'] ?? '') !== '' && $stage === '') {
        $bad('LOYIHA BOSQICHI «' . $values['stage'] . '» roʻyxatda yoʻq');
    }
    if (($values['utilities'] ?? '') !== '' && !array_key_exists($values['utilities'], INTAKE_UTILITIES)) {
        $bad('KOMMUNIKATSIYA «' . $values['utilities'] . '» roʻyxatda yoʻq');
    }
    if (!array_key_exists($values['amount_note'] ?? '', INTAKE_MONEY_NOTES)) {
        $bad('SUMMA HOLATI «' . ($values['amount_note'] ?? '') . '» roʻyxatda yoʻq');
    }

    /* The money, checked hardest: a figure must be a number, a number must
       name its currency, and an empty figure must say why it is empty. */
    $amount = import_number((string) ($values['amount'] ?? ''));
    if ($amount === false) {
        $bad('INVESTITSIYA SUMMASI «' . $values['amount'] . '» raqam emas — faqat raqam yoziladi, masalan 38500000');
        $amount = null;
    }
    if ($amount !== null && $amount > 0) {
        if (!in_array($values['currency'] ?? '', INTAKE_CURRENCIES, true)) {
            $bad('summa yozilgan, VALYUTA koʻrsatilmagan');
        }
        if (($values['amount_note'] ?? '') !== 'Summa aniq') {
            $bad('summa yozilgan, lekin SUMMA HOLATI «' . ($values['amount_note'] ?? '')
                . '» — «Summa aniq» boʻlishi kerak');
        }
        if ($amount < 10000) {
            $warn('summa ' . $amount . ' — juda kichik, mln bilan aralashtirilmaganmi?');
        }
    } elseif (($values['amount_note'] ?? '') === 'Summa aniq') {
        $bad('SUMMA HOLATI «Summa aniq», lekin INVESTITSIYA SUMMASI boʻsh');
    }

    foreach (['land' => 'YER MAYDONI, ga', 'floor_area' => 'BINO MAYDONI, m²'] as $key => $header) {
        if (import_number((string) ($values[$key] ?? '')) === false) {
            $bad('«' . $header . '» raqam emas: «' . $values[$key] . '»');
        }
    }

    /* A head count before anything is built is a forecast, and a region that
       writes "50-100" has said something true that one number would misstate. */
    $jobs = (string) ($values['jobs'] ?? '');
    if ($jobs !== '' && import_number($jobs) === false && preg_match('/^\d+\s*[–—-]\s*\d+$/u', $jobs) !== 1) {
        $bad('«ISH OʻRINLARI» raqam ham, oraliq ham emas: «' . $jobs . '»');
    }

    $coords = (string) ($values['coords'] ?? '');
    if ($coords !== '' && preg_match('/^-?\d{1,2}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/', $coords) !== 1) {
        $warn('KOORDINATA «' . $coords . '» tushunarsiz — «41.3111, 69.2797» koʻrinishida yoziladi');
    }

    $title = (string) ($values['title_uz'] ?? '');
    $upper = function_exists('mb_strtoupper') ? mb_strtoupper($title, 'UTF-8') : strtoupper($title);
    $length = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
    if ($title !== '' && $title === $upper && $length > 4) {
        $warn('LOYIHA NOMI butunlay bosh harflarda — oddiy yozuvda yoziladi');
    }
    if ($title !== '' && $length < 6) {
        $bad('LOYIHA NOMI juda qisqa: «' . $title . '»');
    }
    if (($values['title_ru'] ?? '') === '') {
        $warn('LOYIHA NOMI (RU) boʻsh — ruscha nomi tarjima qilinishi kerak');
    }
    $description = (string) ($values['description'] ?? '');
    $descLength = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
    if ($descLength < 60) {
        $warn('LOYIHA TAVSIFI juda qisqa — kamida uch gap kutiladi');
    }

    return [
        'ok' => $errors === [],
        'row' => [
            'code' => $code,
            'region' => $known['region'],
            'region_uz' => $known['uz'],
            'segment' => $segment,
            'stage' => $stage,
            'amount' => $amount,
            'values' => $values,
            'gaps' => $gaps,
            'excel_row' => $excelRow,
        ],
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

/* ----------------------------------------------------- row -> project record */

/**
 * The project as the site will read it.
 *
 * Shaped exactly like collectIntake() in scripts/build-forum-data.mjs,
 * because the page that renders it cannot tell — and must not be able to tell
 * — whether a project came through a build or through this admin.
 *
 * @return array<string,mixed>
 */
function import_to_project(array $row, string $source): array
{
    $values = $row['values'];
    $lines = static function (string $group) use ($row, $values): array {
        $out = [];
        foreach (import_columns() as $column) {
            if (($column['group'] ?? '') !== $group) {
                continue;
            }
            $raw = trim((string) ($values[$column['key']] ?? ''));
            if ($raw === '') {
                continue;
            }
            $value = match ($column['key']) {
                'stage' => (string) $row['stage'],
                'utilities' => INTAKE_UTILITIES[$raw] ?? $raw,
                'land' => $raw . ' ha',
                'floor_area' => $raw . ' m²',
                'access' => 'Airport / railway: ' . $raw . ' km',
                default => import_unit($raw),
            };
            $out[] = ['label' => $column['label'], 'value' => $value];
        }
        return $out;
    };

    $amount = $row['amount'];
    $currency = (string) ($values['currency'] ?? '');
    $display = $amount !== null && $amount > 0
        ? $currency . ' ' . import_say_money((float) $amount)
        : (INTAKE_MONEY_NOTES[$values['amount_note'] ?? ''] ?? null);

    $place = trim(implode(', ', array_filter([
        trim((string) ($values['district'] ?? '')),
        trim((string) ($values['address'] ?? '')),
    ])));

    $issues = [];
    if ($amount === null || $amount <= 0) {
        $issues[] = 'no investment figure';
    }
    if (($values['title_ru'] ?? '') === '') {
        $issues[] = 'Russian title not supplied by the region';
    }
    $issues[] = 'project description awaits a Russian and English translation';
    foreach ($row['gaps'] as $gap) {
        $issues[] = '“' . $gap . '” not filled in by the region';
    }

    $uz = (string) ($values['title_uz'] ?? '');
    $ru = (string) ($values['title_ru'] ?? '');
    $en = (string) ($values['title_en'] ?? '');
    $description = (string) ($values['description'] ?? '');

    return [
        'id' => strtolower((string) $row['code']),
        'region' => $row['region'],
        'segment' => $row['segment'],
        'title' => $en !== '' ? $en : $uz,
        'titleI18n' => ['uz' => $uz, 'ru' => $ru !== '' ? $ru : $uz, 'en' => $en !== '' ? $en : $uz],
        'subtitle' => $place !== '' ? $place : null,
        'subtitleI18n' => $place !== '' ? ['uz' => $place, 'ru' => $place, 'en' => $place] : null,
        'investment' => $amount !== null && $amount > 0 ? $amount : null,
        'currency' => $amount !== null && $amount > 0 ? $currency : null,
        'investmentDisplay' => $display,
        'metrics' => $lines('metrics'),
        'concept' => $lines('concept'),
        'opportunity' => $lines('opportunity'),
        'whyInvest' => $lines('whyInvest'),
        'place' => [],
        'overview' => $description,
        'overviewI18n' => ['uz' => $description, 'ru' => $description, 'en' => $description],
        'statusNote' => null,
        'sheets' => [$source],
        'photos' => [],
        'issues' => $issues,
    ];
}

/* -------------------------------------------------------------- duplicates */

/**
 * Everything the published portfolio can be asked about a project, once.
 *
 * Built in one pass because the comparison runs for every candidate row
 * against every published project, and a hundred-row list against two hundred
 * and sixty projects is twenty-six thousand comparisons.
 *
 * @return array<string,mixed>
 */
function import_index(array $projects): array
{
    $byId = [];
    $byTitle = [];
    $byShape = [];
    foreach ($projects as $p) {
        if (!is_array($p)) {
            continue;
        }
        $id = (string) ($p['id'] ?? '');
        $byId[$id] = $p;
        foreach (['uz', 'ru', 'en'] as $lang) {
            $title = import_fold((string) ($p['titleI18n'][$lang] ?? ''));
            if ($title !== '') {
                $byTitle[$title][] = $id;
            }
        }
        $plain = import_fold((string) ($p['title'] ?? ''));
        if ($plain !== '') {
            $byTitle[$plain][] = $id;
        }
        $shape = import_shape($p);
        if ($shape !== '') {
            $byShape[$shape][] = $id;
        }
    }
    return ['byId' => $byId, 'byTitle' => $byTitle, 'byShape' => $byShape];
}

/**
 * A project's fingerprint: region, capacity and land area.
 *
 * These three together are what caught the two disguised duplicates in the
 * November list — a hotel sent back under a different name, in the same
 * district, with the same number of rooms on the same plot. Two genuinely
 * different projects agreeing on all three in one region is possible and is
 * why this is a question to the operator and not a refusal.
 */
function import_shape(array $p): string
{
    $metric = static function (array $p, string $label): string {
        foreach ((array) ($p['metrics'] ?? []) as $m) {
            if (is_array($m) && (string) ($m['label'] ?? '') === $label) {
                return import_fold((string) ($m['value'] ?? ''));
            }
        }
        return '';
    };
    $capacity = $metric($p, 'CAPACITY');
    $land = $metric($p, 'LAND AREA');
    if ($capacity === '' && $land === '') {
        return '';
    }
    return (string) ($p['region'] ?? '') . '|' . $capacity . '|' . $land;
}

/**
 * Is this candidate already on the site?
 *
 * Four tests, in the order of how certain they are. The first two refuse;
 * the last two ask.
 *
 * @return array<int,array{level:string,why:string,id:string,title:string}>
 */
function import_clashes(array $project, array $index): array
{
    $out = [];
    $id = (string) $project['id'];
    $name = static fn(string $other): string
        => (string) ($index['byId'][$other]['titleI18n']['uz'] ?? $index['byId'][$other]['title'] ?? $other);

    if (isset($index['byId'][$id])) {
        $out[] = [
            'level' => 'stop',
            'why' => 'Bu kod bilan loyiha allaqachon saytda turibdi.',
            'id' => $id,
            'title' => $name($id),
        ];
        return $out;
    }

    $seen = [];
    foreach (['uz', 'ru', 'en'] as $lang) {
        $title = import_fold((string) ($project['titleI18n'][$lang] ?? ''));
        foreach ($index['byTitle'][$title] ?? [] as $other) {
            if (isset($seen[$other])) {
                continue;
            }
            $seen[$other] = true;
            $out[] = [
                'level' => 'stop',
                'why' => 'Xuddi shu nom saytda bor (' . strtoupper($lang) . ').',
                'id' => $other,
                'title' => $name($other),
            ];
        }
    }
    if ($out !== []) {
        return $out;
    }

    $shape = import_shape($project);
    foreach ($index['byShape'][$shape] ?? [] as $other) {
        $out[] = [
            'level' => 'ask',
            'why' => 'Shu hududda quvvati va yer maydoni bir xil loyiha bor.',
            'id' => $other,
            'title' => $name($other),
        ];
    }

    /* The same street in the same district: two projects can share one, but
       it is worth a human looking at the pair before both are published. */
    $place = import_fold((string) ($project['subtitle'] ?? ''));
    if ($place !== '' && count($out) === 0) {
        foreach ($index['byId'] as $other => $p) {
            if ((string) ($p['region'] ?? '') !== (string) $project['region']) {
                continue;
            }
            if (import_fold((string) ($p['subtitle'] ?? '')) === $place) {
                $out[] = [
                    'level' => 'ask',
                    'why' => 'Manzili saytdagi loyiha bilan bir xil.',
                    'id' => (string) $other,
                    'title' => $name((string) $other),
                ];
            }
        }
    }

    return $out;
}

/* -------------------------------------------------------- the whole report */

/**
 * Read a list and say what would happen if it were accepted.
 *
 * Nothing is written. The result is what public/import.php shows and, when
 * the operator confirms, what it writes.
 *
 * @return array<string,mixed>
 */
function import_report(string $file, string $sourceName, array $published): array
{
    $open = import_open($file);
    $index = import_index($published);
    $missing = import_missing_columns($open['map']);

    $seen = [];
    $candidates = [];
    $refused = [];
    $skipped = 0;
    $titlesInFile = [];

    foreach ($open['rows'] as $number => $cells) {
        if ($number <= $open['header']) {
            continue;
        }
        $values = [];
        foreach ($open['map'] as $column => $key) {
            $values[$key] = trim((string) ($cells[$column] ?? ''));
        }
        /* A row with nothing in it at all is the end of the list, not a row
           with thirty missing cells. */
        if (implode('', $values) === '') {
            continue;
        }

        $checked = import_check_row($values, $number, $seen);
        if (!empty($checked['skipped'])) {
            $skipped++;
            continue;
        }
        if (!$checked['ok']) {
            $refused[] = [
                'row' => $number,
                'code' => strtoupper(trim((string) ($values['code'] ?? ''))),
                'title' => (string) ($values['title_uz'] ?? ''),
                'errors' => $checked['errors'],
                'warnings' => $checked['warnings'],
            ];
            continue;
        }

        $project = import_to_project($checked['row'], $sourceName);

        /* Two rows in one file naming the same project. The code test above
           catches the same code; this catches the same name under two codes,
           which is what a list assembled from several offices does. */
        $fold = import_fold((string) $project['titleI18n']['uz']);
        if (isset($titlesInFile[$fold])) {
            $refused[] = [
                'row' => $number,
                'code' => (string) $checked['row']['code'],
                'title' => (string) $project['titleI18n']['uz'],
                'errors' => ['Shu nom faylning ' . $titlesInFile[$fold] . '-qatorida ham bor.'],
                'warnings' => $checked['warnings'],
            ];
            continue;
        }
        $titlesInFile[$fold] = $number;

        $clashes = import_clashes($project, $index);
        $stop = false;
        foreach ($clashes as $clash) {
            if ($clash['level'] === 'stop') {
                $stop = true;
            }
        }

        $candidates[] = [
            'row' => $number,
            'code' => (string) $checked['row']['code'],
            'project' => $project,
            'warnings' => $checked['warnings'],
            'clashes' => $clashes,
            'state' => $stop ? 'duplicate' : ($clashes === [] ? 'new' : 'ask'),
            'photos' => [],
        ];
    }

    return [
        'sheet' => $open['sheet'],
        'header_row' => $open['header'],
        'missing_columns' => $missing,
        'candidates' => $candidates,
        'refused' => $refused,
        'skipped' => $skipped,
        'counts' => [
            'new' => count(array_filter($candidates, static fn($c) => $c['state'] === 'new')),
            'ask' => count(array_filter($candidates, static fn($c) => $c['state'] === 'ask')),
            'duplicate' => count(array_filter($candidates, static fn($c) => $c['state'] === 'duplicate')),
            'refused' => count($refused),
        ],
    ];
}

/* ------------------------------------------------------------- the photographs */

/**
 * Match the pictures in a zip to the codes in the list.
 *
 * The join is the file name, which is what the template tells the region to
 * do: BUX-01_1.jpg. A picture pasted into a spreadsheet cell belongs to no
 * row — it floats over the grid — which is the whole reason the photographs
 * travel beside the list rather than inside it.
 *
 * @param array<int,string> $codes the codes the list actually carries
 * @return array{byCode:array<string,array<int,array{name:string,entry:array}>>,orphans:string[],ignored:string[]}
 */
function import_match_photos(string $zip, array $codes): array
{
    $byCode = [];
    $orphans = [];
    $ignored = [];
    $known = [];
    foreach ($codes as $code) {
        $known[strtoupper($code)] = true;
    }

    foreach (zip_list($zip) as $entry) {
        $base = zip_safe_name($entry['name']);
        if ($base === '') {
            $ignored[] = $entry['name'];
            continue;
        }
        if (preg_match('/^([A-Za-z]{3}-\d{2,3})[_-](\d{1,2})\.(jpe?g|png|webp)$/i', $base, $m) !== 1) {
            $ignored[] = $base;
            continue;
        }
        $code = strtoupper($m[1]);
        if (!isset($known[$code])) {
            $orphans[] = $base;
            continue;
        }
        $byCode[$code][] = ['name' => $entry['name'], 'base' => $base, 'n' => (int) $m[2], 'size' => $entry['size']];
    }

    foreach ($byCode as $code => $list) {
        usort($list, static fn(array $a, array $b): int => $a['n'] <=> $b['n']);
        $byCode[$code] = array_slice($list, 0, PHOTO_MAX_PER_PROJECT);
    }
    sort($orphans);
    sort($ignored);
    return ['byCode' => $byCode, 'orphans' => $orphans, 'ignored' => $ignored];
}
