<?php
/**
 * The project photographs the admin adds, and where they live.
 *
 * The site reads a photograph as two files under its own `photos/` folder:
 * `<stem>-thumb.webp` for the strip and `<stem>-full.webp` for the panel. A
 * project's `photos` array is a list of stems. That is the whole contract,
 * and it is the one `npm run photos` already writes — so a photograph
 * uploaded here is indistinguishable from one pulled out of a deck, which is
 * the point: the page needs no second code path to show it.
 *
 * ## Why the file is kept twice
 *
 * The deploy copies the built site over the live one with `rsync --delete`.
 * Anything inside the web root that is not in the build is removed — which
 * would be every photograph the organiser had uploaded, silently, on the next
 * push.
 *
 * So the uploads live in the data directory, which the deploy excludes, and
 * are copied into the web root whenever the site is published. The deploy
 * already ends by publishing (bin/publish.php), so a photograph lost to
 * `--delete` is back before the deploy has finished. The data directory is
 * the original; the web root is a copy that may be thrown away at any time.
 *
 * ## What is done to an uploaded image
 *
 * It is decoded, turned if its EXIF says it was taken sideways, scaled to the
 * two widths the site uses and re-encoded as WebP. Nothing of the original
 * file survives that: not its EXIF, not its colour profile, not whatever was
 * appended after the image data by somebody who wanted it to be two files at
 * once. Re-encoding is the only image sanitisation worth the name.
 */

declare(strict_types=1);

require_once __DIR__ . '/site.php';

/** The two widths the site asks for, as `npm run photos` writes them. */
const PHOTO_SIZES = ['thumb' => 220, 'full' => 1000];
const PHOTO_QUALITY = ['thumb' => 70, 'full' => 76];
/** Bigger than this and it is not a photograph of a building site. */
const PHOTO_MAX_BYTES = 12582912;   /* 12 MB */
const PHOTO_MAX_PIXELS = 25000000;  /* a 5000×5000 image */
/** More than this per project and the panel is a slideshow nobody scrolls. */
const PHOTO_MAX_PER_PROJECT = 8;

/**
 * Can this server make a WebP at all?
 *
 * Without GD, or with a GD built without WebP, there is nothing useful to do
 * with an uploaded JPEG: the site asks for `-full.webp` by name. The upload
 * is refused with the apk line rather than attempted — a half-working upload
 * that writes a JPEG under a .webp name is a broken picture on a public page.
 */
function photos_can(): bool
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
        return false;
    }
    $info = function_exists('gd_info') ? gd_info() : [];
    return !empty($info['WebP Support']);
}

function photos_complaint_server(): string
{
    if (photos_can()) {
        return '';
    }
    if (!function_exists('imagecreatetruecolor')) {
        return 'Serverda GD kutubxonasi yoʻq — rasm yuklab boʻlmaydi. Oʻrnatish: apk add php84-gd';
    }
    return 'Serverdagi GD WebP formatini qoʻllamaydi — rasm yuklab boʻlmaydi. '
        . 'Oʻrnatish: apk add php84-gd libwebp';
}

/** Where the originals of this admin's own photographs are kept. */
function photos_dir(array $config): string
{
    return rtrim((string) $config['data_dir'], '/\\') . '/photos';
}

/** Where the site reads them from. */
function photos_site_dir(array $config): string
{
    return site_dir($config) . '/photos';
}

/** Every stem this admin owns. A build's own photographs are not in here. */
function photos_own(array $config): array
{
    $dir = photos_dir($config);
    if (!is_dir($dir)) {
        return [];
    }
    $stems = [];
    foreach ((array) @scandir($dir) as $name) {
        if (is_string($name) && preg_match('/^(.+)-full\.webp$/', $name, $m) === 1) {
            $stems[$m[1]] = true;
        }
    }
    return array_keys($stems);
}

function photos_is_own(array $config, string $stem): bool
{
    return $stem !== ''
        && photos_stem_ok($stem)
        && is_file(photos_dir($config) . '/' . $stem . '-full.webp');
}

/** A stem is a file name fragment. It may be nothing else. */
function photos_stem_ok(string $stem): bool
{
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,60}$/', $stem) === 1;
}

/**
 * Take one uploaded image and write the two files the site wants.
 *
 * `$hint` only shapes the name, so that a directory listing on the server is
 * readable; the uniqueness comes from the random half. The `adm-` opening
 * marks it as the admin's own, which is what photos_own() reads and what
 * keeps a build's photographs from ever being deleted from here.
 *
 * @return string the stem
 */
function photos_store(array $config, string $bytes, string $hint = ''): string
{
    if (!photos_can()) {
        throw new RuntimeException(photos_complaint_server());
    }
    $complaint = photos_complaint_image($bytes);
    if ($complaint !== '') {
        throw new RuntimeException($complaint);
    }

    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        throw new RuntimeException('Rasmni ochib boʻlmadi — fayl buzilgan yoki rasm emas.');
    }
    $image = photos_upright($image, $bytes);

    $dir = photos_dir($config);
    if (!is_dir($dir) && !@mkdir($dir, 0o770, true) && !is_dir($dir)) {
        imagedestroy($image);
        throw new RuntimeException('Rasmlar papkasini yaratib boʻlmadi: ' . $dir);
    }

    $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', substr($hint, 0, 10)));
    $stem = 'adm' . ($slug === '' ? '' : $slug) . '-' . bin2hex(random_bytes(5));

    try {
        foreach (PHOTO_SIZES as $label => $width) {
            $scaled = photos_scale($image, $width);
            $path = $dir . '/' . $stem . '-' . $label . '.webp';
            $ok = @imagewebp($scaled, $path, PHOTO_QUALITY[$label]);
            imagedestroy($scaled);
            if (!$ok) {
                throw new RuntimeException('Rasmni saqlab boʻlmadi: ' . $path);
            }
            @chmod($path, 0o640);
        }
    } finally {
        imagedestroy($image);
    }

    photos_sync($config);
    return $stem;
}

/**
 * Is this thing an image this code will take?
 *
 * The test is the bytes, never the file name: a name is what the sender
 * typed. getimagesizefromstring() reads the header and says what it really
 * is, and only the three formats the site's own pipeline accepts are let
 * through.
 */
function photos_complaint_image(string $bytes): string
{
    $size = strlen($bytes);
    if ($size === 0) {
        return 'Fayl boʻsh.';
    }
    if ($size > PHOTO_MAX_BYTES) {
        return 'Rasm juda katta: ' . photos_say_size($size)
            . '. Ruxsat etilgani — ' . photos_say_size(PHOTO_MAX_BYTES) . '.';
    }
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info)) {
        return 'Bu rasm fayli emas.';
    }
    [$w, $h, $type] = [$info[0] ?? 0, $info[1] ?? 0, $info[2] ?? 0];
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return 'Faqat JPEG, PNG va WebP qabul qilinadi.';
    }
    if ($w < 320 || $h < 200) {
        return 'Rasm juda kichkina: ' . $w . '×' . $h . '. Kamida 320×200 boʻlsin.';
    }
    if ($w * $h > PHOTO_MAX_PIXELS) {
        return 'Rasmning piksellari juda koʻp: ' . $w . '×' . $h . '. Kichraytirib yuboring.';
    }
    return '';
}

/** "2.4 MB", for a message a person reads. */
function photos_say_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ' '), '0'), '.') . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

/**
 * Turn a photograph the way it was taken.
 *
 * A telephone writes the sensor's orientation into EXIF and leaves the pixels
 * alone. GD reads the pixels. Without this, half the photographs a regional
 * office sends arrive on the page lying on their side.
 */
function photos_upright(GdImage $image, string $bytes): GdImage
{
    if (!function_exists('exif_read_data') || !function_exists('imagerotate')) {
        return $image;
    }
    $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
    $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 0) : 0;
    $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
    if ($angle === 0) {
        return $image;
    }
    $turned = @imagerotate($image, $angle, 0);
    if ($turned === false) {
        return $image;
    }
    imagedestroy($image);
    return $turned;
}

/** A copy at the given width, never enlarged, on a white ground. */
function photos_scale(GdImage $image, int $width): GdImage
{
    $w = imagesx($image);
    $h = imagesy($image);
    $to = min($width, $w);
    $height = max(1, (int) round($h * ($to / $w)));

    $out = imagecreatetruecolor($to, $height);
    /* A transparent PNG becomes a white photograph rather than a black one:
       the panel it lands in is light, and WebP would otherwise carry the
       transparency into a layout that has no background behind it. */
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefilledrectangle($out, 0, 0, $to, $height, $white);
    imagecopyresampled($out, $image, 0, 0, 0, 0, $to, $height, $w, $h);
    return $out;
}

/**
 * Put every photograph this admin owns where the site can read it.
 *
 * Copies only what is missing or has changed, so a publish with two hundred
 * photographs already in place costs two hundred stat calls and no writes.
 * Never deletes from the web root: a stem the site no longer mentions is a
 * file nothing fetches, and a deploy clears it anyway.
 *
 * @return array{copied:int,failed:int}
 */
function photos_sync(array $config): array
{
    $from = photos_dir($config);
    if (!is_dir($from)) {
        return ['copied' => 0, 'failed' => 0];
    }
    $to = photos_site_dir($config);
    if (!is_dir($to) && !@mkdir($to, 0o775, true) && !is_dir($to)) {
        return ['copied' => 0, 'failed' => 1];
    }

    $copied = 0;
    $failed = 0;
    foreach ((array) @scandir($from) as $name) {
        if (!is_string($name) || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*\.webp$/', $name) !== 1) {
            continue;
        }
        $src = $from . '/' . $name;
        $dst = $to . '/' . $name;
        if (is_file($dst) && filesize($dst) === filesize($src) && filemtime($dst) >= filemtime($src)) {
            continue;
        }
        /* Written beside and renamed, so the site never fetches half a file. */
        $tmp = $dst . '.' . getmypid() . '.tmp';
        if (@copy($src, $tmp) && @rename($tmp, $dst)) {
            @chmod($dst, 0o644);
            $copied++;
        } else {
            @unlink($tmp);
            $failed++;
        }
    }
    return ['copied' => $copied, 'failed' => $failed];
}

/**
 * Delete one of this admin's own photographs.
 *
 * Refuses a stem that came out of a build: those belong to the repository and
 * come back with the next deploy, so deleting them here would be a file that
 * disappears and returns. Taking such a photograph off a project is done by
 * removing it from that project's list, which is an override like any other.
 */
function photos_delete(array $config, string $stem): bool
{
    if (!photos_is_own($config, $stem)) {
        return false;
    }
    $ok = false;
    foreach (array_keys(PHOTO_SIZES) as $label) {
        foreach ([photos_dir($config), photos_site_dir($config)] as $dir) {
            $file = $dir . '/' . $stem . '-' . $label . '.webp';
            if (is_file($file)) {
                $ok = @unlink($file) || $ok;
            }
        }
    }
    return $ok;
}

/**
 * Does the site actually have both files for this stem?
 *
 * Used by the editor to mark a reference the build no longer carries — a
 * project whose photograph was dropped upstream shows an empty frame, and the
 * editor should say so rather than leave the organiser wondering.
 */
function photos_present(array $config, string $stem): bool
{
    if (!photos_stem_ok($stem)) {
        return false;
    }
    foreach ([photos_site_dir($config), photos_dir($config)] as $dir) {
        if (is_file($dir . '/' . $stem . '-full.webp')) {
            return true;
        }
    }
    return false;
}

/**
 * Where the admin's own page should fetch a thumbnail from.
 *
 * The site and the admin are one host — nginx serves the built site at `/`
 * and this at `/admin/` — so a photograph is one absolute path away, and the
 * admin's content policy can stay at `img-src 'self'`.
 */
function site_photo_url(array $config, string $stem): string
{
    return '/photos/' . rawurlencode($stem) . '-thumb.webp';
}

/**
 * How much is in the photograph folder, for the page that reports it.
 *
 * @return array{count:int,bytes:int}
 */
function photos_usage(array $config): array
{
    $dir = photos_dir($config);
    if (!is_dir($dir)) {
        return ['count' => 0, 'bytes' => 0];
    }
    $count = 0;
    $bytes = 0;
    foreach ((array) @scandir($dir) as $name) {
        if (is_string($name) && str_ends_with($name, '-full.webp')) {
            $count++;
        }
        if (is_string($name) && str_ends_with($name, '.webp')) {
            $bytes += (int) @filesize($dir . '/' . $name);
        }
    }
    return ['count' => $count, 'bytes' => $bytes];
}
