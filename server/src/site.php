<?php
/**
 * Editing the published site from the admin.
 *
 * The site is static files. Everything it says comes out of two JSON files it
 * fetches at runtime — `data/forum-2026.json` and `data/content.json` — so
 * changing one of those changes the live page the moment it is saved. No
 * rebuild, no deploy, no node on the server.
 *
 * What this file adds is the part that makes that safe to do twice.
 *
 * ## Why an overlay and not an editor
 *
 * Those two files are build output. `npm run forum` reads the organiser's own
 * project decks and writes them, and it will be run again — a new batch of
 * sheets arrives, somebody corrects a figure, the portfolio grows. An admin
 * that edited the published file directly would have its work thrown away by
 * the next deploy, silently, and nobody would know which numbers had been
 * lost.
 *
 * So the admin never edits the published file. It records *changes* —
 * "event.city.uz is now this", "hide that project" — in overrides.json, and
 * the published file is rewritten as base + overrides. The base is a copy of
 * the build output exactly as it was deployed.
 *
 * When a deploy lands, the published file stops matching the hash we wrote,
 * which is how this file knows: it takes a fresh base and re-applies every
 * override on top. A corrected figure from the decks comes through; an
 * override on a field nobody touched upstream survives. An override on a
 * field the decks have since changed still wins, and is listed in the admin
 * as such so it can be dropped.
 *
 * ## Paths
 *
 * An override is keyed by a dotted path: `event.city.uz`,
 * `portfolio.totalUsd`. One rule is not dotted-path-standard and earns its
 * keep: inside a list, a segment that is not a number is matched against the
 * elements' own `id`. `portfolio.projects.andijan-safari-tourism.titleI18n.uz`
 * therefore goes on reading the right project after the list is re-sorted,
 * which it is on every build.
 */

declare(strict_types=1);

/** The files the site reads, and what the admin calls them. */
const SITE_FILES = [
    'forum' => ['file' => 'forum-2026.json', 'label' => 'Forum sayti'],
    'content' => ['file' => 'content.json', 'label' => 'Umumiy maʼlumotlar'],
];

/**
 * Where the built site is.
 *
 * Configured, because the admin and the site are two directories the nginx
 * block below ties together and only the installer knows where they are. The
 * fallback is the repository's own build output, which is what `npm run
 * server` serves.
 */
function site_dir(array $config): string
{
    $dir = trim((string) ($config['site_dir'] ?? ''));
    if ($dir === '') {
        $dir = dirname(__DIR__, 2) . '/site-2-silkroad/dist';
    }
    return rtrim(str_replace('\\', '/', $dir), '/');
}

function site_published_file(array $config, string $key): string
{
    return site_dir($config) . '/data/' . SITE_FILES[$key]['file'];
}

function site_state_file(array $config, string $key, string $kind): string
{
    return rtrim((string) $config['data_dir'], '/\\') . "/site-{$key}-{$kind}.json";
}

/* ------------------------------------------------------------------- json */

function site_read_json(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Write a file the way the rest of this backend writes files: to a temporary
 * name in the same directory and then rename, so a reader never catches a
 * half-written JSON — and the site's own fetch is exactly such a reader.
 */
function site_write_json(string $file, array $data): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
        throw new RuntimeException("Papkani yaratib boʻlmadi: {$dir}");
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON yozib boʻlmadi: ' . json_last_error_msg());
    }
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json) === false) {
        throw new RuntimeException("Yozib boʻlmadi: {$file}");
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException("Oʻrniga qoʻyib boʻlmadi: {$file}");
    }
}

/* ------------------------------------------------------------ overrides io */

function site_overrides(array $config, string $key): array
{
    $data = site_read_json(site_state_file($config, $key, 'overrides')) ?? [];
    return [
        'values' => is_array($data['values'] ?? null) ? $data['values'] : [],
        'hidden' => is_array($data['hidden'] ?? null) ? array_values($data['hidden']) : [],
        'added' => is_array($data['added'] ?? null) ? array_values($data['added']) : [],
        'published_sha' => (string) ($data['published_sha'] ?? ''),
        'updated_at' => (string) ($data['updated_at'] ?? ''),
        'updated_count' => (int) ($data['updated_count'] ?? 0),
    ];
}

function site_save_overrides(array $config, string $key, array $overrides): void
{
    $overrides['updated_at'] = gmdate('c');
    $overrides['updated_count'] = (int) ($overrides['updated_count'] ?? 0) + 1;
    site_write_json(site_state_file($config, $key, 'overrides'), $overrides);
}

/* ------------------------------------------------------------------- base */

/**
 * The build output, as deployed.
 *
 * Taken from the published file the first time, and re-taken whenever that
 * file stops matching the hash of what we last wrote — which is how a deploy
 * announces itself to a backend that has no other way of hearing about one.
 */
function site_base(array $config, string $key, ?string &$note = null): ?array
{
    $published = site_published_file($config, $key);
    $baseFile = site_state_file($config, $key, 'base');
    $base = site_read_json($baseFile);
    $overrides = site_overrides($config, $key);

    if (!is_file($published)) {
        $note = 'published-missing';
        return $base;
    }
    $sha = sha1_file($published);

    if ($base === null) {
        $note = 'base-taken';
    } elseif ($overrides['published_sha'] !== '' && $sha !== $overrides['published_sha']) {
        // Somebody replaced the published file — a deploy, or an edit by hand.
        $note = 'redeployed';
    } else {
        return $base;
    }

    $fresh = site_read_json($published);
    if ($fresh === null) {
        $note = 'published-unreadable';
        return $base;
    }
    site_write_json($baseFile, $fresh);
    return $fresh;
}

/* ------------------------------------------------------------------ paths */

/** Split `a.b.c` into segments. */
function site_path_parts(string $path): array
{
    return array_values(array_filter(explode('.', $path), static fn($s) => $s !== ''));
}

/** Is `$node` a list — a plain array with 0..n-1 keys? */
function site_is_list(mixed $node): bool
{
    return is_array($node) && ($node === [] || array_is_list($node));
}

/**
 * Where a segment points inside a node: a key, a numeric index, or — inside a
 * list — the index of the element whose own `id` is that segment.
 */
function site_index_for(mixed $node, string $segment): int|string|null
{
    if (!is_array($node)) {
        return null;
    }
    if (site_is_list($node)) {
        if (ctype_digit($segment)) {
            return array_key_exists((int) $segment, $node) ? (int) $segment : null;
        }
        foreach ($node as $i => $item) {
            if (is_array($item) && (string) ($item['id'] ?? '') === $segment) {
                return $i;
            }
        }
        return null;
    }
    return array_key_exists($segment, $node) ? $segment : null;
}

function site_get(array $data, string $path): mixed
{
    $node = $data;
    foreach (site_path_parts($path) as $segment) {
        $at = site_index_for($node, $segment);
        if ($at === null) {
            return null;
        }
        $node = $node[$at];
    }
    return $node;
}

function site_has(array $data, string $path): bool
{
    $node = $data;
    foreach (site_path_parts($path) as $segment) {
        $at = site_index_for($node, $segment);
        if ($at === null) {
            return false;
        }
        $node = $node[$at];
    }
    return true;
}

/** Sets `$path` in `$data`, creating nothing that is not already there. */
function site_set(array &$data, string $path, mixed $value): bool
{
    $parts = site_path_parts($path);
    if ($parts === []) {
        return false;
    }
    $node = &$data;
    foreach ($parts as $i => $segment) {
        $at = site_index_for($node, $segment);
        if ($at === null) {
            return false;
        }
        if ($i === count($parts) - 1) {
            $node[$at] = $value;
            return true;
        }
        $node = &$node[$at];
    }
    return false;
}

/* --------------------------------------------------------------- publish */

/**
 * Totals, recomputed.
 *
 * Hiding a project changes the counts the page prints above the list, and a
 * portfolio that says 83 while showing 81 is worse than one that cannot be
 * edited at all.
 */
function site_recount(array $forum): array
{
    if (!isset($forum['portfolio']['projects']) || !is_array($forum['portfolio']['projects'])) {
        return $forum;
    }
    $projects = $forum['portfolio']['projects'];
    $regions = [];
    $segments = [];
    $totalUsd = 0.0;
    $usd = 0;
    $uzs = 0;
    $open = 0;

    foreach ($projects as $p) {
        $region = $p['region'] ?? null;
        if (is_string($region) && $region !== '') {
            $regions[$region] = ($regions[$region] ?? 0) + 1;
        }
        $segment = $p['segment'] ?? null;
        if (is_string($segment) && $segment !== '') {
            $segments[$segment] = ($segments[$segment] ?? 0) + 1;
        }
        $amount = $p['investment'] ?? null;
        $currency = $p['currency'] ?? null;
        if (is_numeric($amount) && $currency === 'USD') {
            $totalUsd += (float) $amount;
            $usd++;
        } elseif (is_numeric($amount) && $currency === 'UZS') {
            $uzs++;
        } else {
            $open++;
        }
    }

    $forum['portfolio']['regionCounts'] = $regions;
    $forum['portfolio']['segmentCounts'] = $segments;
    $forum['portfolio']['totalUsd'] = $totalUsd;
    $forum['portfolio']['statedUsdCount'] = $usd;
    $forum['portfolio']['statedUzsCount'] = $uzs;
    $forum['portfolio']['openCount'] = $open;
    return $forum;
}

/**
 * The build output with the admin's own projects appended.
 *
 * This, and not the build output alone, is what the editor treats as the
 * base. A project added here has to be editable afterwards like any other —
 * its title corrected, a figure fixed — and an override is keyed by a path
 * that has to exist in the base for site_set() to find it. Appending first
 * is what makes `portfolio.projects.<new-id>.titleI18n.uz` a real path.
 *
 * The added projects are stored whole rather than as overrides because there
 * is nothing upstream for them to be a change *to*: the next build will not
 * know about them, and that is exactly why they survive it.
 */
function site_with_added(array $base, array $overrides, string $key): array
{
    if ($key !== 'forum') {
        return $base;
    }
    $added = $overrides['added'] ?? [];
    if ($added === [] || !isset($base['portfolio']['projects'])) {
        return $base;
    }
    /*
     * An id that is already there wins, and the added copy is dropped.
     *
     * Two ways that happens. The harmless one is this function being called
     * twice on the same array. The one that matters is a project added here
     * and later shipped by the build as well — a region sends the list, the
     * organiser adds one row by hand on the Friday, the Monday build carries
     * it too. Appending blindly would publish that building twice, which is
     * the exact fault this whole import page exists to prevent.
     */
    $have = [];
    foreach ($base['portfolio']['projects'] as $p) {
        if (is_array($p)) {
            $have[(string) ($p['id'] ?? '')] = true;
        }
    }
    foreach ($added as $p) {
        $id = is_array($p) ? (string) ($p['id'] ?? '') : '';
        if ($id === '' || isset($have[$id])) {
            continue;
        }
        $have[$id] = true;
        $base['portfolio']['projects'][] = $p;
    }
    return $base;
}

/** base + added + overrides, as the site will read it. */
function site_merged(array $base, array $overrides, string $key, array &$stale = []): array
{
    $data = site_with_added($base, $overrides, $key);

    foreach ($overrides['values'] as $path => $value) {
        if (!site_set($data, (string) $path, $value)) {
            $stale[] = (string) $path;
        }
    }

    if ($key === 'forum') {
        $added = $overrides['added'];
        $hidden = array_flip($overrides['hidden']);
        if ($hidden !== [] && isset($data['portfolio']['projects'])) {
            $data['portfolio']['projects'] = array_values(array_filter(
                $data['portfolio']['projects'],
                static fn($p) => !isset($hidden[(string) ($p['id'] ?? '')]),
            ));
        }
        if ($added !== [] || $hidden !== []) {
            $data = site_recount($data);
        }
    }

    return $data;
}

/**
 * Write the merged file where the site reads it, and remember its hash.
 *
 * Returns what happened, for the admin to show: the number of overrides
 * applied, and any whose path no longer exists upstream.
 */
function site_publish(array $config, string $key): array
{
    $note = null;
    $base = site_base($config, $key, $note);
    if ($base === null) {
        throw new RuntimeException(
            'Saytning maʼlumot fayli topilmadi: ' . site_published_file($config, $key) .
            ' — server/.env dagi SITE_DIR ni tekshiring.',
        );
    }
    $overrides = site_overrides($config, $key);
    $stale = [];
    $merged = site_merged($base, $overrides, $key, $stale);

    $published = site_published_file($config, $key);
    site_write_json($published, $merged);
    clearstatcache(true, $published);

    /*
     * The photographs, back into the web root.
     *
     * A deploy copies the build over the live site with `rsync --delete`, so
     * every photograph uploaded from the admin is removed from the web root
     * whenever one lands. The deploy's last step is to publish, which is
     * here, so they are put back before it finishes. See src/photos.php.
     */
    /*
     * Required here rather than at the top of the file, and on purpose.
     *
     * photos.php requires this file — it needs site_dir() — so requiring it
     * back at the top is a cycle. Doing it at the point of use means every
     * caller of site_publish() gets the photographs put back whether or not
     * it thought to load that file: bin/publish.php, which is the one the
     * deploy runs, did not, and the photographs stayed deleted.
     */
    require_once __DIR__ . '/photos.php';
    $photos = photos_sync($config);

    $overrides['published_sha'] = (string) sha1_file($published);
    site_write_json(site_state_file($config, $key, 'overrides'), $overrides);

    return [
        'applied' => count($overrides['values']),
        'hidden' => count($overrides['hidden']),
        'added' => count($overrides['added']),
        'photos' => $photos['copied'],
        'photos_failed' => $photos['failed'],
        'stale' => $stale,
        'base' => $note,
        'file' => $published,
    ];
}

/* ------------------------------------------------------- projects of our own */

/**
 * Add a whole project to the portfolio.
 *
 * It is stored in the overlay rather than written into the published file,
 * for the same reason an edited field is: the published file is build output
 * and the next deploy replaces it. A project in `added` is re-appended every
 * time the site is published, including by the deploy's own last step.
 *
 * @param array<string,mixed> $project a record shaped like the build's own
 */
function site_add_project(array $config, array $project): void
{
    $overrides = site_overrides($config, 'forum');
    $overrides['added'][] = $project;
    site_save_overrides($config, 'forum', $overrides);
}

/** Take one back out, with every override that was keyed to it. */
function site_remove_added(array $config, string $id): bool
{
    $overrides = site_overrides($config, 'forum');
    $before = count($overrides['added']);
    $overrides['added'] = array_values(array_filter(
        $overrides['added'],
        static fn($p) => (string) (is_array($p) ? ($p['id'] ?? '') : '') !== $id,
    ));
    if (count($overrides['added']) === $before) {
        return false;
    }
    $prefix = 'portfolio.projects.' . $id . '.';
    foreach (array_keys($overrides['values']) as $path) {
        if (str_starts_with((string) $path, $prefix)) {
            unset($overrides['values'][$path]);
        }
    }
    $overrides['hidden'] = array_values(array_diff($overrides['hidden'], [$id]));
    site_save_overrides($config, 'forum', $overrides);
    return true;
}

/** Was this project added from the admin rather than built? */
function site_is_added(array $overrides, string $id): bool
{
    foreach ($overrides['added'] ?? [] as $p) {
        if (is_array($p) && (string) ($p['id'] ?? '') === $id) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------- the editor */

/**
 * What a leaf is, for the form.
 *
 * Only the shapes the two files actually hold: a string (one line or several),
 * a number, a boolean, a list of strings. Anything else is shown but not
 * edited, which is the honest answer for a field whose shape the editor does
 * not understand — better than letting somebody type into it and lose it.
 */
function site_kind(mixed $value): string
{
    if (is_bool($value)) {
        return 'bool';
    }
    if (is_int($value) || is_float($value)) {
        return 'number';
    }
    if (is_string($value)) {
        // A date earns a picker. The forum's own dates are the field most
        // likely to be edited here, and "2026-11-25" typed into a text box
        // is a format to get wrong — the site reads it back with
        // `new Date()`, which gives it no second chance.
        if (site_is_date($value)) {
            return 'date';
        }
        return mb_strlen_safe($value) > 90 || str_contains($value, "\n") ? 'text' : 'line';
    }
    if ($value === null) {
        return 'line';
    }
    if (site_is_list($value)) {
        foreach ($value as $item) {
            if (!is_string($item)) {
                return 'nested';
            }
        }
        return 'lines';
    }
    return 'nested';
}

function mb_strlen_safe(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

/** An ISO calendar date, `YYYY-MM-DD`, and a real one. */
function site_is_date(string $value): bool
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/**
 * Reads a posted field back into the type the base holds there.
 *
 * The form is all strings; the file is not, and a totalUsd that arrives as
 * "1389550000" and is stored as a string makes the page print nothing. The
 * base value decides the type, so a field can never change shape by being
 * edited.
 */
function site_cast(mixed $original, string $posted): mixed
{
    if (is_bool($original)) {
        return $posted === '1' || $posted === 'on';
    }
    if (is_int($original)) {
        return (int) str_replace([' ', ' '], '', $posted);
    }
    if (is_float($original)) {
        return (float) str_replace([' ', ' ', ','], ['', '', '.'], $posted);
    }
    if (site_is_list($original)) {
        $lines = preg_split('/\r\n|\r|\n/', $posted) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn($s) => $s !== ''));
    }
    $value = trim($posted);
    if ($value === '' && $original === null) {
        return null;
    }
    // A date field keeps the date it had unless what arrived is one too: an
    // empty or half-typed box would otherwise leave the site, the badges and
    // the PDF covers printing "Invalid Date" where the forum's dates go.
    if (is_string($original) && site_is_date($original) && !site_is_date($value)) {
        return $original;
    }
    return $value;
}

/** True when the edit says the same thing the base already said. */
function site_same(mixed $a, mixed $b): bool
{
    if (is_float($a) || is_float($b)) {
        return abs((float) $a - (float) $b) < 1e-9;
    }
    return $a === $b;
}
