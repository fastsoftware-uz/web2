<?php
/**
 * A QR code, drawn as SVG.
 *
 * It is here rather than in a library because this directory has no composer
 * and is deployed by copying it: adding a dependency would mean adding a
 * package manager to a server that does not have one. The alternative was to
 * draw the code in the browser with a JavaScript library, which would mean a
 * ticket that is blank when it is printed from a saved file or rendered by a
 * mail client. A badge whose barcode depends on JavaScript is not a badge.
 *
 * What is implemented: byte mode, error correction level M, versions 1 to 10
 * — up to 213 bytes, where the longest address this ticket will ever carry is
 * about sixty. Level M recovers from roughly 15% damage, which is the level
 * event badges are normally printed at: enough for a fold through the code or
 * a thumbprint over it, without the density of Q or H.
 *
 * Output is SVG rather than PNG because there is no GD on the target server
 * either, and because a vector code prints at the printer's own resolution
 * instead of the one we guessed. One <rect> per dark module, no gaps.
 *
 * The encoder is checked against a real decoder — see qa/ticket.mjs, which
 * renders the SVG and reads it back with jsQR.
 */

declare(strict_types=1);

/** Total codewords, EC codewords per block, and the block layout, at level M. */
const QR_SPEC = [
    //          total  ec/block  [[blocks, data codewords], …]
    1  => [26,  10, [[1, 16]]],
    2  => [44,  16, [[1, 28]]],
    3  => [70,  26, [[1, 44]]],
    4  => [100, 18, [[2, 32]]],
    5  => [134, 24, [[2, 43]]],
    6  => [172, 16, [[4, 27]]],
    7  => [196, 18, [[4, 31]]],
    8  => [242, 22, [[2, 38], [2, 39]]],
    9  => [292, 22, [[3, 36], [2, 37]]],
    10 => [346, 26, [[4, 43], [1, 44]]],
];

/** Where the alignment patterns sit, by version. */
const QR_ALIGN = [
    1  => [],
    2  => [6, 18],
    3  => [6, 22],
    4  => [6, 26],
    5  => [6, 30],
    6  => [6, 34],
    7  => [6, 22, 38],
    8  => [6, 24, 42],
    9  => [6, 26, 46],
    10 => [6, 28, 50],
];

/** Bits left over after the last codeword, by version. */
const QR_REMAINDER = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0];

/* ------------------------------------------------------------ GF(256) */

/**
 * The field the Reed–Solomon arithmetic happens in, as a pair of tables.
 *
 * Built once per request rather than written out: it is thirty lines of
 * constants that a loop derives in a microsecond, and a table nobody can read
 * is a table nobody can check.
 */
function qr_gf(): array
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) {
            $x ^= 0x11d; // the primitive polynomial QR codes are defined over
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }
    return $tables = [$exp, $log];
}

function qr_mul(int $a, int $b): int
{
    if ($a === 0 || $b === 0) {
        return 0;
    }
    [$exp, $log] = qr_gf();
    return $exp[$log[$a] + $log[$b]];
}

/** The generator polynomial for `$n` error-correction codewords. */
function qr_generator(int $n): array
{
    [$exp] = qr_gf();
    $poly = [1];
    for ($i = 0; $i < $n; $i++) {
        $next = array_fill(0, count($poly) + 1, 0);
        foreach ($poly as $j => $coefficient) {
            // Multiply by (x + α^i), highest power first: the term keeps its
            // index when x raises its degree, because the array grows at the
            // end. Writing these two lines the other way round builds the
            // polynomial backwards, which still divides and still produces
            // ten plausible-looking codewords — none of them the right ones.
            $next[$j] ^= $coefficient;
            $next[$j + 1] ^= qr_mul($coefficient, $exp[$i]);
        }
        $poly = $next;
    }
    return $poly;
}

/** The error-correction codewords for one block. */
function qr_ec(array $data, int $n): array
{
    $generator = qr_generator($n);
    $remainder = array_merge($data, array_fill(0, $n, 0));
    $count = count($data);
    for ($i = 0; $i < $count; $i++) {
        $factor = $remainder[$i];
        if ($factor === 0) {
            continue;
        }
        foreach ($generator as $j => $coefficient) {
            $remainder[$i + $j] ^= qr_mul($coefficient, $factor);
        }
    }
    return array_slice($remainder, $count, $n);
}

/* ------------------------------------------------------------- encoding */

/** The smallest version that holds `$length` bytes at level M. */
function qr_version(int $length): int
{
    foreach (QR_SPEC as $version => [, $ecPerBlock, $blocks]) {
        $data = 0;
        foreach ($blocks as [$n, $size]) {
            $data += $n * $size;
        }
        // 4 bits of mode, 8 or 16 of length, the data itself.
        $header = 4 + ($version < 10 ? 8 : 16);
        if ($header + $length * 8 <= $data * 8) {
            return $version;
        }
    }
    throw new RuntimeException('Too long for a version 10 QR code: ' . $length . ' bytes');
}

/** The data codewords, padded, split into blocks, error-corrected, interleaved. */
function qr_codewords(string $text, int $version): array
{
    [, $ecPerBlock, $layout] = QR_SPEC[$version];
    $blocks = [];
    foreach ($layout as [$n, $size]) {
        for ($i = 0; $i < $n; $i++) {
            $blocks[] = $size;
        }
    }
    $capacity = array_sum($blocks);

    $bits = '0100'; // byte mode
    $bits .= str_pad(decbin(strlen($text)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
    for ($i = 0, $n = strlen($text); $i < $n; $i++) {
        $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
    }
    // Terminator, then out to a whole codeword, then the two pad bytes in turn.
    $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
    $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
    $pad = ['11101100', '00010001'];
    for ($i = 0; strlen($bits) < $capacity * 8; $i++) {
        $bits .= $pad[$i % 2];
    }

    $all = [];
    for ($i = 0; $i < $capacity; $i++) {
        $all[] = bindec(substr($bits, $i * 8, 8));
    }

    $data = [];
    $ec = [];
    $at = 0;
    foreach ($blocks as $size) {
        $block = array_slice($all, $at, $size);
        $at += $size;
        $data[] = $block;
        $ec[] = qr_ec($block, $ecPerBlock);
    }

    /* Interleaved: the first codeword of every block, then the second of
       every block, and so on — which is what spreads a torn corner across all
       of them instead of destroying one. */
    $out = [];
    $longest = max(array_map('count', $data));
    for ($i = 0; $i < $longest; $i++) {
        foreach ($data as $block) {
            if (isset($block[$i])) {
                $out[] = $block[$i];
            }
        }
    }
    for ($i = 0; $i < $ecPerBlock; $i++) {
        foreach ($ec as $block) {
            $out[] = $block[$i];
        }
    }
    return $out;
}

/* -------------------------------------------------------------- matrix */

/**
 * The fixed patterns, and a map of which cells they occupy.
 *
 * Returns [$matrix, $reserved]: the modules, and the cells the data must step
 * over — finders, timing, alignment, the format strips and, from version 7,
 * the version blocks.
 */
function qr_frame(int $version): array
{
    $size = 17 + 4 * $version;
    $matrix = array_fill(0, $size, array_fill(0, $size, 0));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));

    $finder = static function (int $top, int $left) use (&$matrix, &$reserved, $size): void {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $cy = $top + $y;
                $cx = $left + $x;
                if ($cy < 0 || $cx < 0 || $cy >= $size || $cx >= $size) {
                    continue;
                }
                $inRing = ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6))
                    || ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6));
                $inCore = $y >= 2 && $y <= 4 && $x >= 2 && $x <= 4;
                $matrix[$cy][$cx] = ($inRing || $inCore) ? 1 : 0;
                $reserved[$cy][$cx] = true;
            }
        }
    };
    $finder(0, 0);
    $finder(0, $size - 7);
    $finder($size - 7, 0);

    // Timing patterns.
    for ($i = 8; $i < $size - 8; $i++) {
        $bit = ($i % 2 === 0) ? 1 : 0;
        $matrix[6][$i] = $bit;
        $reserved[6][$i] = true;
        $matrix[$i][6] = $bit;
        $reserved[$i][6] = true;
    }

    // Alignment patterns, except where they would sit on a finder.
    $centres = QR_ALIGN[$version];
    foreach ($centres as $cy) {
        foreach ($centres as $cx) {
            if (($cy === 6 && $cx === 6)
                || ($cy === 6 && $cx === $size - 7)
                || ($cy === $size - 7 && $cx === 6)) {
                continue;
            }
            for ($y = -2; $y <= 2; $y++) {
                for ($x = -2; $x <= 2; $x++) {
                    $on = max(abs($y), abs($x)) !== 1;
                    $matrix[$cy + $y][$cx + $x] = $on ? 1 : 0;
                    $reserved[$cy + $y][$cx + $x] = true;
                }
            }
        }
    }

    // The dark module, and the strips the format information goes in.
    $matrix[$size - 8][8] = 1;
    $reserved[$size - 8][8] = true;
    for ($i = 0; $i <= 8; $i++) {
        if (!$reserved[8][$i]) {
            $reserved[8][$i] = true;
        }
        if (!$reserved[$i][8]) {
            $reserved[$i][8] = true;
        }
    }
    for ($i = 0; $i < 8; $i++) {
        $reserved[8][$size - 1 - $i] = true;
        $reserved[$size - 1 - $i][8] = true;
    }

    // Version information, from version 7 up.
    if ($version >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $row = intdiv($i, 3);
            $col = $i % 3;
            $reserved[$row][$size - 11 + $col] = true;
            $reserved[$size - 11 + $col][$row] = true;
        }
    }

    return [$matrix, $reserved, $size];
}

/** The data bits, laid in the upward-and-downward columns from the right. */
function qr_place(array $matrix, array $reserved, int $size, array $codewords, int $remainder): array
{
    $bits = '';
    foreach ($codewords as $codeword) {
        $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
    }
    $bits .= str_repeat('0', $remainder);

    $at = 0;
    $up = true;
    for ($right = $size - 1; $right > 0; $right -= 2) {
        if ($right === 6) {
            $right--; // the vertical timing pattern is not a data column
        }
        for ($i = 0; $i < $size; $i++) {
            $y = $up ? $size - 1 - $i : $i;
            foreach ([0, 1] as $offset) {
                $x = $right - $offset;
                if ($reserved[$y][$x]) {
                    continue;
                }
                $matrix[$y][$x] = $at < strlen($bits) ? (int) $bits[$at] : 0;
                $at++;
            }
        }
        $up = !$up;
    }
    return $matrix;
}

function qr_mask(int $mask, int $y, int $x): bool
{
    return match ($mask) {
        0 => ($y + $x) % 2 === 0,
        1 => $y % 2 === 0,
        2 => $x % 3 === 0,
        3 => ($y + $x) % 3 === 0,
        4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
        5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
        6 => (((($y * $x) % 2) + (($y * $x) % 3)) % 2) === 0,
        7 => (((($y + $x) % 2) + (($y * $x) % 3)) % 2) === 0,
        default => false,
    };
}

/** How bad a masked code looks to a scanner. Lower is better. */
function qr_penalty(array $m, int $size): int
{
    $score = 0;

    // Runs of five or more.
    for ($i = 0; $i < $size; $i++) {
        foreach ([true, false] as $byRow) {
            $run = 1;
            for ($j = 1; $j < $size; $j++) {
                $a = $byRow ? $m[$i][$j] : $m[$j][$i];
                $b = $byRow ? $m[$i][$j - 1] : $m[$j - 1][$i];
                if ($a === $b) {
                    $run++;
                    continue;
                }
                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
                $run = 1;
            }
            if ($run >= 5) {
                $score += 3 + ($run - 5);
            }
        }
    }

    // Two-by-two blocks of one colour.
    for ($y = 0; $y < $size - 1; $y++) {
        for ($x = 0; $x < $size - 1; $x++) {
            $v = $m[$y][$x];
            if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                $score += 3;
            }
        }
    }

    // The finder-like sequence, either way round, in rows and in columns.
    $patterns = [[1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0], [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1]];
    for ($i = 0; $i < $size; $i++) {
        for ($j = 0; $j <= $size - 11; $j++) {
            foreach ($patterns as $pattern) {
                $row = true;
                $col = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($m[$i][$j + $k] !== $pattern[$k]) {
                        $row = false;
                    }
                    if ($m[$j + $k][$i] !== $pattern[$k]) {
                        $col = false;
                    }
                    if (!$row && !$col) {
                        break;
                    }
                }
                $score += ($row ? 40 : 0) + ($col ? 40 : 0);
            }
        }
    }

    // How far the proportion of dark modules is from half.
    $dark = 0;
    foreach ($m as $row) {
        $dark += array_sum($row);
    }
    $percent = (int) (abs($dark * 100 / ($size * $size) - 50) / 5);
    $score += $percent * 10;

    return $score;
}

/** The fifteen bits of format information for level M and this mask. */
function qr_format(int $mask): int
{
    $data = (0b00 << 3) | $mask; // 00 is error-correction level M
    $bch = $data << 10;
    for ($i = 14; $i >= 10; $i--) {
        if ($bch & (1 << $i)) {
            $bch ^= 0b10100110111 << ($i - 10);
        }
    }
    return (($data << 10) | $bch) ^ 0b101010000010010;
}

/** The eighteen bits of version information, from version 7 up. */
function qr_version_bits(int $version): int
{
    $bch = $version << 12;
    for ($i = 17; $i >= 12; $i--) {
        if ($bch & (1 << $i)) {
            $bch ^= 0b1111100100101 << ($i - 12);
        }
    }
    return ($version << 12) | $bch;
}

/** The finished module matrix for `$text`. */
function qr_matrix(string $text): array
{
    $version = qr_version(strlen($text));
    [$frame, $reserved, $size] = qr_frame($version);
    $laid = qr_place($frame, $reserved, $size, qr_codewords($text, $version), QR_REMAINDER[$version]);

    $best = null;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $m = $laid;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$reserved[$y][$x] && qr_mask($mask, $y, $x)) {
                    $m[$y][$x] ^= 1;
                }
            }
        }

        // The format bits, which are part of what the scanner reads and so
        // part of what is scored.
        /**
         * The fifteen format bits, twice.
         *
         * The first copy runs down the column beside the top-left finder and
         * turns the corner along the row; the second is split between the
         * other two finders. The two copies are mirror images of each other,
         * which makes them very easy to write out transposed — and a code
         * with its format strips swapped is one a scanner cannot start
         * reading at all, however good the data underneath is.
         */
        $format = qr_format($mask);
        for ($i = 0; $i < 15; $i++) {
            $bit = ($format >> $i) & 1;
            // First copy: down column 8, then left along row 8.
            if ($i < 6) {
                $m[$i][8] = $bit;
            } elseif ($i === 6) {
                $m[7][8] = $bit;
            } elseif ($i === 7) {
                $m[8][8] = $bit;
            } elseif ($i === 8) {
                $m[8][7] = $bit;
            } else {
                $m[8][14 - $i] = $bit;
            }
            // Second copy: along row 8 from the right, then up column 8.
            if ($i < 8) {
                $m[8][$size - 1 - $i] = $bit;
            } else {
                $m[$size - 15 + $i][8] = $bit;
            }
        }

        if ($version >= 7) {
            $bits = qr_version_bits($version);
            for ($i = 0; $i < 18; $i++) {
                $bit = ($bits >> $i) & 1;
                $row = intdiv($i, 3);
                $col = $i % 3;
                $m[$row][$size - 11 + $col] = $bit;
                $m[$size - 11 + $col][$row] = $bit;
            }
        }

        $score = qr_penalty($m, $size);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $m;
        }
    }

    return $best;
}

/**
 * The code as an SVG element, sized by CSS.
 *
 * The quiet zone is four modules, as the specification requires — a code
 * printed hard against a border is a code a phone hunts for.
 */
function qr_svg(string $text, string $class = '', string $colour = '#15193a'): string
{
    $m = qr_matrix($text);
    $size = count($m);
    $quiet = 4;
    $side = $size + $quiet * 2;

    $path = '';
    foreach ($m as $y => $row) {
        $x = 0;
        while ($x < $size) {
            if ($row[$x] !== 1) {
                $x++;
                continue;
            }
            $run = 1;
            while ($x + $run < $size && $row[$x + $run] === 1) {
                $run++;
            }
            // One run of dark modules is one rectangle; neighbouring runs
            // share an edge, so the printer never leaves a hairline between.
            $path .= 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h' . $run . 'v1h-' . $run . 'z';
            $x += $run;
        }
    }

    $attr = $class === '' ? '' : ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"';
    return '<svg' . $attr . ' viewBox="0 0 ' . $side . ' ' . $side . '" role="img" '
        . 'shape-rendering="crispEdges" xmlns="http://www.w3.org/2000/svg">'
        . '<rect width="' . $side . '" height="' . $side . '" fill="#fff"/>'
        . '<path d="' . $path . '" fill="' . htmlspecialchars($colour, ENT_QUOTES) . '"/>'
        . '</svg>';
}
