<?php
/**
 * Reading a .zip, with or without the zip extension.
 *
 * Two things arrive at the importer as zip files: the photographs, in a
 * folder the region zipped up, and the project list itself — .xlsx is a zip
 * with XML inside it. Both have to be readable on a server where
 * `php84-zip` was never installed, because Alpine ships every extension as
 * its own package and the one that is missing is never the one you expect.
 *
 * So: ZipArchive when it is there, and the central directory parsed by hand
 * when it is not. Both go through the same three limits, because a zip is
 * also the classic way to hand a server a file that is small to send and
 * enormous to open.
 *
 *   - at most ZIP_MAX_ENTRIES names;
 *   - at most ZIP_MAX_UNPACKED bytes unpacked in total;
 *   - at most ZIP_MAX_ENTRY_BYTES for any one of them.
 *
 * And every name is checked before it is used for anything: `../` and an
 * absolute path are the whole of zip-slip, and a zip is a file a stranger
 * composed.
 */

declare(strict_types=1);

const ZIP_MAX_ENTRIES = 600;
const ZIP_MAX_UNPACKED = 268435456;   /* 256 MB */
const ZIP_MAX_ENTRY_BYTES = 33554432; /*  32 MB */

/**
 * The names inside a zip, directories left out.
 *
 * @return array<int,array{name:string,size:int}>
 */
function zip_list(string $file): array
{
    $out = [];
    foreach (zip_entries($file) as $entry) {
        if ($entry['dir']) {
            continue;
        }
        $out[] = ['name' => $entry['name'], 'size' => $entry['size']];
    }
    return $out;
}

/**
 * One entry's bytes, or null when it is not there or is too large.
 */
function zip_get(string $file, string $name): ?string
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return null;
        }
        $at = $zip->locateName($name);
        if ($at === false) {
            $zip->close();
            return null;
        }
        $stat = $zip->statIndex($at);
        if (!is_array($stat) || (int) $stat['size'] > ZIP_MAX_ENTRY_BYTES) {
            $zip->close();
            return null;
        }
        $data = $zip->getFromIndex($at, ZIP_MAX_ENTRY_BYTES);
        $zip->close();
        return is_string($data) ? $data : null;
    }

    foreach (zip_entries($file) as $entry) {
        if ($entry['name'] === $name && !$entry['dir']) {
            return zip_inflate($file, $entry);
        }
    }
    return null;
}

/**
 * Every entry, as a plain description.
 *
 * @return array<int,array{name:string,size:int,csize:int,method:int,offset:int,dir:bool}>
 */
function zip_entries(string $file): array
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException('Zip faylni ochib boʻlmadi.');
        }
        $n = $zip->numFiles;
        if ($n > ZIP_MAX_ENTRIES) {
            $zip->close();
            throw new RuntimeException('Zip ichida juda koʻp fayl: ' . $n . ' ta, ruxsat ' . ZIP_MAX_ENTRIES . ' ta.');
        }
        $out = [];
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) {
                continue;
            }
            $name = (string) $stat['name'];
            $size = (int) $stat['size'];
            $total += $size;
            if ($total > ZIP_MAX_UNPACKED) {
                $zip->close();
                throw new RuntimeException('Zip ochilganda juda katta boʻlib ketadi — shubhali fayl.');
            }
            $out[] = [
                'name' => $name,
                'size' => $size,
                'csize' => (int) $stat['comp_size'],
                'method' => (int) $stat['comp_method'],
                'offset' => 0,
                'dir' => str_ends_with($name, '/'),
            ];
        }
        $zip->close();
        return $out;
    }
    return zip_entries_by_hand($file);
}

/**
 * The central directory, read without the extension.
 *
 * Only what is needed: where each local header is, how big the entry is, and
 * whether it is stored or deflated. Zip64, encryption and the other shapes a
 * zip can take are refused by name rather than half-understood — a file the
 * importer cannot read is a message to the sender, and a file it reads wrongly
 * is a wrong project on a public page.
 *
 * @return array<int,array{name:string,size:int,csize:int,method:int,offset:int,dir:bool}>
 */
function zip_entries_by_hand(string $file): array
{
    $fh = @fopen($file, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Zip faylni ochib boʻlmadi.');
    }
    try {
        $length = (int) filesize($file);
        /* The end record is at most 22 bytes plus a comment of up to 64 KB. */
        $tailLength = min($length, 65557);
        fseek($fh, $length - $tailLength);
        $tail = (string) fread($fh, $tailLength);
        $at = strrpos($tail, "PK\x05\x06");
        if ($at === false) {
            throw new RuntimeException('Bu zip fayl emas (yoki buzilgan).');
        }
        $end = unpack('vdisk/vcdDisk/vhere/vtotal/Vsize/Voffset/vcomment', substr($tail, $at + 4, 18));
        if (!is_array($end)) {
            throw new RuntimeException('Zip katalogini oʻqib boʻlmadi.');
        }
        $count = (int) $end['total'];
        if ($count === 0xFFFF || (int) $end['offset'] === 0xFFFFFFFF) {
            throw new RuntimeException('Zip64 fayllari qoʻllab-quvvatlanmaydi — zipni oddiy usulda qayta yarating.');
        }
        if ($count > ZIP_MAX_ENTRIES) {
            throw new RuntimeException('Zip ichida juda koʻp fayl: ' . $count . ' ta.');
        }

        fseek($fh, (int) $end['offset']);
        $dir = (string) fread($fh, (int) $end['size']);
        $out = [];
        $p = 0;
        $total = 0;
        for ($i = 0; $i < $count; $i++) {
            if (substr($dir, $p, 4) !== "PK\x01\x02") {
                throw new RuntimeException('Zip katalogi buzilgan.');
            }
            $head = unpack(
                'vmadeBy/vneeded/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnameLen/vextraLen/vcommentLen/vdiskNo/vattrIn/VattrEx/Voffset',
                substr($dir, $p + 4, 42),
            );
            if (!is_array($head)) {
                throw new RuntimeException('Zip katalogi buzilgan.');
            }
            if (((int) $head['flag'] & 0x0001) !== 0) {
                throw new RuntimeException('Zip parol bilan yopilgan — parolsiz zip yuboring.');
            }
            $name = substr($dir, $p + 46, (int) $head['nameLen']);
            $size = (int) $head['size'];
            if ($size > ZIP_MAX_ENTRY_BYTES) {
                throw new RuntimeException('Zip ichidagi fayl juda katta: ' . $name);
            }
            $total += $size;
            if ($total > ZIP_MAX_UNPACKED) {
                throw new RuntimeException('Zip ochilganda juda katta boʻlib ketadi — shubhali fayl.');
            }
            $out[] = [
                'name' => $name,
                'size' => $size,
                'csize' => (int) $head['csize'],
                'method' => (int) $head['method'],
                'offset' => (int) $head['offset'],
                'dir' => str_ends_with($name, '/'),
            ];
            $p += 46 + (int) $head['nameLen'] + (int) $head['extraLen'] + (int) $head['commentLen'];
        }
        return $out;
    } finally {
        fclose($fh);
    }
}

/** One entry's bytes, read through its local header. */
function zip_inflate(string $file, array $entry): ?string
{
    $fh = @fopen($file, 'rb');
    if ($fh === false) {
        return null;
    }
    try {
        fseek($fh, $entry['offset']);
        if (fread($fh, 4) !== "PK\x03\x04") {
            return null;
        }
        $local = unpack('vneeded/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnameLen/vextraLen', (string) fread($fh, 26));
        if (!is_array($local)) {
            return null;
        }
        fseek($fh, $entry['offset'] + 30 + (int) $local['nameLen'] + (int) $local['extraLen']);
        $raw = (string) fread($fh, max(1, $entry['csize']));
        if ((int) $entry['method'] === 0) {
            return substr($raw, 0, $entry['size']);
        }
        if ((int) $entry['method'] !== 8) {
            return null;
        }
        if (!function_exists('gzinflate')) {
            return null;
        }
        $data = @gzinflate($raw);
        return $data === false ? null : $data;
    } finally {
        fclose($fh);
    }
}

/**
 * Is this name safe to use, and what is its last part?
 *
 * Returns the base name when the entry is one the importer may look at, and
 * an empty string when it is not: an absolute path, anything climbing with
 * `..`, a Windows drive letter, a backslash, a control character, or one of
 * the metadata folders a Mac puts in every archive it makes.
 */
function zip_safe_name(string $name): string
{
    if ($name === '' || strlen($name) > 240) {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
        return '';
    }
    $plain = str_replace('\\', '/', $name);
    if (str_starts_with($plain, '/') || preg_match('#^[A-Za-z]:#', $plain) === 1) {
        return '';
    }
    $parts = explode('/', $plain);
    foreach ($parts as $part) {
        if ($part === '..') {
            return '';
        }
    }
    if (preg_match('#(^|/)(__MACOSX|\.DS_Store|Thumbs\.db)(/|$)#i', $plain) === 1) {
        return '';
    }
    $base = (string) end($parts);
    return $base === '' ? '' : $base;
}
