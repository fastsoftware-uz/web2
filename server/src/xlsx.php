<?php
/**
 * Reading an .xlsx, without a spreadsheet library.
 *
 * The project list arrives as the file the working group filled in — the
 * template this repository generates — and the admin has to be able to read
 * it on the organiser's own server. Composer is not installed there and will
 * not be; neither is php-xml, necessarily. So this reads the four parts of
 * the format that carry values and ignores everything else a spreadsheet
 * puts in a workbook.
 *
 *   xl/workbook.xml            which sheets exist, and in what order
 *   xl/_rels/workbook.xml.rels which file each of them is
 *   xl/sharedStrings.xml       the strings, which cells point into
 *   xl/worksheets/sheetN.xml   the cells
 *
 * The parsing is done with patterns rather than a DOM. That is a deliberate
 * trade and it is safe in exactly this case: these four files are written by
 * a spreadsheet, their shape is fixed, and the values are decoded through
 * one function that handles the entities and the `_x000D_` escapes Excel
 * writes. It also means no XML parser is reachable from a stranger's file —
 * no entity expansion, no external entity, no billion laughs.
 *
 * What is deliberately not read: formatting, formulas (their last computed
 * value is read instead), merged cells, pictures. A picture pasted into a
 * sheet belongs to no row — that is why photographs travel as files beside
 * the list, which is the rule the template itself states.
 */

declare(strict_types=1);

require_once __DIR__ . '/zipread.php';

/** At most this many rows are read out of one sheet. */
const XLSX_MAX_ROWS = 2000;
/** And at most this many columns of each. */
const XLSX_MAX_COLS = 60;

/**
 * The sheets, in the workbook's own order.
 *
 * @return array<int,array{name:string,path:string}>
 */
function xlsx_sheets(string $file): array
{
    $workbook = zip_get($file, 'xl/workbook.xml');
    if ($workbook === null) {
        throw new RuntimeException('Bu .xlsx fayl emas — ichida xl/workbook.xml yoʻq.');
    }
    $rels = zip_get($file, 'xl/_rels/workbook.xml.rels') ?? '';

    /* rId -> the file it names, which is relative to xl/. */
    $target = [];
    if (preg_match_all('#<Relationship\b([^>]*)/?>#', $rels, $all, PREG_SET_ORDER) > 0) {
        foreach ($all as $one) {
            $id = xlsx_attr($one[1], 'Id');
            $to = xlsx_attr($one[1], 'Target');
            if ($id !== '' && $to !== '') {
                $target[$id] = 'xl/' . ltrim(str_replace('/xl/', '', $to), '/');
            }
        }
    }

    $out = [];
    if (preg_match_all('#<sheet\b([^>]*)/?>#', $workbook, $all, PREG_SET_ORDER) > 0) {
        foreach ($all as $i => $one) {
            $name = xlsx_text(xlsx_attr($one[1], 'name'));
            $rid = xlsx_attr($one[1], 'r:id');
            $path = $target[$rid] ?? ('xl/worksheets/sheet' . ($i + 1) . '.xml');
            $out[] = ['name' => $name, 'path' => $path];
        }
    }
    if ($out === []) {
        throw new RuntimeException('Faylda birorta ham varaq topilmadi.');
    }
    return $out;
}

/**
 * The shared string table, as a plain list.
 *
 * @return array<int,string>
 */
function xlsx_strings(string $file): array
{
    $raw = zip_get($file, 'xl/sharedStrings.xml');
    if ($raw === null) {
        return [];
    }
    $out = [];
    if (preg_match_all('#<si>(.*?)</si>#s', $raw, $all) > 0) {
        foreach ($all[1] as $si) {
            /* A styled string is cut into runs; the value is the runs joined. */
            $text = '';
            if (preg_match_all('#<t[^>]*>(.*?)</t>#s', $si, $parts) > 0) {
                $text = implode('', $parts[1]);
            }
            $out[] = xlsx_text($text);
        }
    }
    return $out;
}

/**
 * One sheet, as rows of cells keyed by column letter.
 *
 * A row that the file does not mention is not in the result: a spreadsheet
 * writes only the rows that have something in them, and inventing the gaps
 * would turn "the region left a gap in the middle of the list" into two
 * hundred empty projects.
 *
 * @return array<int,array<string,string>> row number => column letter => value
 */
function xlsx_rows(string $file, string $path, array $strings): array
{
    $raw = zip_get($file, $path);
    if ($raw === null) {
        throw new RuntimeException('Varaqni oʻqib boʻlmadi: ' . $path);
    }
    /*
     * A sheet larger than this is not a project list.
     *
     * A list of a hundred projects is a few hundred kilobytes of XML. Eight
     * megabytes is a sheet somebody has pasted a database into, or a zip
     * built to be small to send and expensive to read — and the patterns
     * below run over the whole string before the row cap can take effect.
     */
    if (strlen($raw) > 8388608) {
        throw new RuntimeException(
            'Varaq juda katta (' . round(strlen($raw) / 1048576, 1) . ' MB) — '
            . 'faqat loyihalar jadvali qolgan nusxasini yuboring.',
        );
    }

    $rows = [];
    $found = preg_match_all('#<row\b([^>]*)>(.*?)</row>#s', $raw, $all, PREG_SET_ORDER);
    if ($found === false) {
        /* PCRE gave up — a backtrack or recursion limit. Said out loud,
           because the alternative is reporting an empty list as if the file
           had no projects in it. */
        throw new RuntimeException('Varaqni oʻqib boʻlmadi — fayl juda murakkab yoki buzilgan.');
    }
    if ($found === 0) {
        return [];
    }
    foreach ($all as $one) {
        $number = (int) xlsx_attr($one[1], 'r');
        if ($number <= 0 || $number > XLSX_MAX_ROWS) {
            continue;
        }
        $cells = [];
        if (preg_match_all('#<c\b([^>]*?)(?:/>|>(.*?)</c>)#s', $one[2], $found, PREG_SET_ORDER) > 0) {
            foreach ($found as $cell) {
                $ref = xlsx_attr($cell[1], 'r');
                $column = (string) preg_replace('/\d+/', '', $ref);
                if ($column === '' || strlen($column) > 3) {
                    continue;
                }
                if (count($cells) >= XLSX_MAX_COLS) {
                    break;
                }
                $value = xlsx_cell_value($cell[1], $cell[2] ?? '', $strings);
                if ($value !== '') {
                    $cells[$column] = $value;
                }
            }
        }
        if ($cells !== []) {
            $rows[$number] = $cells;
        }
    }
    ksort($rows);
    return $rows;
}

/** The value of one `<c>`, whatever kind it is. */
function xlsx_cell_value(string $attrs, string $inner, array $strings): string
{
    $type = xlsx_attr($attrs, 't');

    if ($type === 'inlineStr') {
        $text = '';
        if (preg_match_all('#<t[^>]*>(.*?)</t>#s', $inner, $parts) > 0) {
            $text = implode('', $parts[1]);
        }
        return xlsx_text($text);
    }

    if (preg_match('#<v[^>]*>(.*?)</v>#s', $inner, $m) !== 1) {
        return '';
    }
    $value = xlsx_text($m[1]);

    if ($type === 's') {
        $at = (int) $value;
        return $strings[$at] ?? '';
    }
    if ($type === 'b') {
        return $value === '1' ? 'ha' : 'yoʻq';
    }
    if ($type === 'e') {
        /* A formula that evaluated to an error: #N/A, #REF!. Kept as it is,
           so the importer can say which cell and the sender can see why. */
        return $value;
    }
    if ($type === '' || $type === 'n') {
        return xlsx_number($value);
    }
    return $value;
}

/**
 * A number as the sheet meant it, not as a float prints.
 *
 * Excel stores 1.27 as 1.2699999999999999 often enough to matter, and a land
 * area that reads "1.2699999999999999 ha" on a public page is a figure this
 * code changed. Fifteen significant digits is what the format itself promises,
 * so that is where it is rounded, and a whole number keeps no decimal point.
 */
function xlsx_number(string $value): string
{
    if ($value === '' || !is_numeric($value)) {
        return $value;
    }
    $float = (float) $value;
    if (floor($float) === $float && abs($float) < 1e15) {
        return number_format($float, 0, '.', '');
    }
    $text = rtrim(rtrim(sprintf('%.10F', $float), '0'), '.');
    return $text === '' || $text === '-' ? '0' : $text;
}

/** One attribute out of a start tag. */
function xlsx_attr(string $attrs, string $name): string
{
    $quoted = preg_quote($name, '#');
    if (preg_match('#\b' . $quoted . '="([^"]*)"#', $attrs, $m) === 1) {
        return $m[1];
    }
    return '';
}

/**
 * XML text as the reader typed it.
 *
 * The five XML entities, the numeric ones, and Excel's own `_x000D_` for a
 * carriage return. Then the control characters are taken out and the
 * whitespace squeezed, which is the same treatment every other value in this
 * backend gets on the way in — see app_str().
 */
function xlsx_text(string $raw): string
{
    if ($raw === '') {
        return '';
    }
    $text = html_entity_decode($raw, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    $text = (string) preg_replace_callback(
        '/_x([0-9A-Fa-f]{4})_/',
        static function (array $m): string {
            $code = (int) hexdec($m[1]);
            /* Only the ones a spreadsheet actually escapes; anything else
               stays as it was typed rather than becoming a character the
               sender did not write. */
            return in_array($code, [0x0A, 0x0D, 0x09], true) ? chr($code) : $m[0];
        },
        $text,
    );
    $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text);
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

/**
 * Is this file an .xlsx at all?
 *
 * The magic bytes of a zip, and the one part every workbook has. A .xls from
 * 2003 and a .csv renamed are the two things that actually arrive, and both
 * deserve a sentence rather than a parse error.
 */
function xlsx_complaint(string $file): string
{
    $head = (string) @file_get_contents($file, false, null, 0, 8);
    if (strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0) {
        return 'Bu eski .xls fayl. Excelda oching va «.xlsx» koʻrinishida saqlang.';
    }
    if (strncmp($head, 'PK', 2) !== 0) {
        return 'Bu .xlsx fayl emas. Excel yoki LibreOffice dan «.xlsx» koʻrinishida saqlang.';
    }
    try {
        if (zip_get($file, 'xl/workbook.xml') === null) {
            return 'Zip ochildi, lekin ichida Excel varaqlari yoʻq.';
        }
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    return '';
}
