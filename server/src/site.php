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
    $out = [
        'values' => is_array($data['values'] ?? null) ? $data['values'] : [],
        'hidden' => is_array($data['hidden'] ?? null) ? array_values($data['hidden']) : [],
        'added' => is_array($data['added'] ?? null) ? array_values($data['added']) : [],
        'published_sha' => (string) ($data['published_sha'] ?? ''),
        'updated_at' => (string) ($data['updated_at'] ?? ''),
        'updated_count' => (int) ($data['updated_count'] ?? 0),
    ];
    /*
     * The speaker list, and whether it is the operator's.
     *
     * Having a list of one's own and having an empty list mean opposite
     * things to site_with_speakers(): no list of my own means the speakers
     * the build ships stand, an empty list means the operator removed them
     * all and a deploy must not hand them back. The list alone cannot tell
     * the two apart, and an installation that has been publishing since
     * before the build shipped any speakers already has `speakers: []`
     * written into this file — site_publish() writes the array back
     * verbatim, so the empty default the old code handed it was saved. So
     * the answer is a flag of its own, written only by site_save_speakers()
     * when somebody arranges the list through the admin.
     */
    $out['speakers'] = is_array($data['speakers'] ?? null) ? array_values($data['speakers']) : [];
    $out['speakers_set'] = ($data['speakers_set'] ?? false) === true;
    /*
     * Speakers the operator has taken off, by id.
     *
     * The other half of the same answer. A deploy must not hand back
     * somebody they removed — but it must hand over somebody the organiser
     * has just added, and a list that only ever says "these are mine"
     * cannot do both. This says which absences are deliberate; every other
     * absence is a name that has arrived since.
     */
    $out['speakers_removed'] = array_values(array_filter(
        array_map('strval', is_array($data['speakers_removed'] ?? null) ? $data['speakers_removed'] : []),
        static fn($id) => $id !== '',
    ));
    return $out;
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
    $shipped = is_array($base['portfolio']['photosForAdded'] ?? null)
        ? $base['portfolio']['photosForAdded']
        : [];
    foreach ($added as $p) {
        $id = is_array($p) ? (string) ($p['id'] ?? '') : '';
        if ($id === '' || isset($have[$id])) {
            continue;
        }
        $have[$id] = true;
        $base['portfolio']['projects'][] = site_added_with_shipped_photos($p, $shipped);
    }
    unset($base['portfolio']['photosForAdded']);
    return $base;
}

/**
 * A picture for a project only the admin has.
 *
 * The organiser types a project in here when the build has not got it yet,
 * and the picture of it arrives later, with a deploy: twenty-five Xorazm
 * projects on 20 November 2026, typed under exactly the ids the organiser's
 * own picture folders use. Those records live in the overlay, where no build
 * can reach them, so the build ships the pictures as a map from id to stem
 * and this puts them on.
 *
 * Only on a project that has none. A picture uploaded here is the operator's
 * choice and nothing arriving with a deploy may replace it — the same rule
 * a speaker's portrait follows, for the same reason.
 */
function site_added_with_shipped_photos(array $project, array $shipped): array
{
    if (!$shipped) {
        return $project;
    }
    $id = (string) ($project['id'] ?? '');
    if ($id === '' || !isset($shipped[$id]) || !is_array($shipped[$id])) {
        return $project;
    }
    $has = $project['photos'] ?? null;
    if (is_array($has) && $has !== []) {
        return $project;
    }
    $project['photos'] = array_values(array_filter(
        array_map('strval', $shipped[$id]),
        static fn(string $stem): bool => $stem !== '',
    ));
    return $project;
}

/**
 * The forum file with the admin's speaker list in it.
 *
 * The list is settled in the weeks before the forum and changes until the
 * morning of it, so unlike every other field here the overlay does not
 * record a *change* to something upstream: it holds the whole list and
 * replaces whatever was published. That is why nothing a later deploy
 * brings can overwrite an arrangement made here.
 *
 * The build may ship speakers of its own — it did not for the first month,
 * and then four were added to the data file — and those stand until the
 * operator arranges a list of their own. Which is why this asks whether
 * they have arranged one rather than whether the list is empty: an operator
 * who has deliberately removed every speaker must not have the build's four
 * handed back to them on the next save. See site_overrides() for why that
 * question needs a flag of its own.
 *
 * Appending it before the overrides are applied is what makes
 * `speakers.<id>.role.uz` a path site_set() can find, so a speaker is
 * editable from the generic editor as well as from their own page.
 */
function site_with_speakers(
    array $base,
    array $overrides,
    string $key,
    ?array $config = null,
): array {
    if ($key !== 'forum') {
        return $base;
    }
    if (!($overrides['speakers_set'] ?? false)) {
        return $base;
    }
    $shipped = is_array($base['speakers'] ?? null) ? $base['speakers'] : [];
    $base['speakers'] = site_speakers_with_shipped_photos(
        site_speakers_without_withdrawn(
            site_speakers_with_new_arrivals(
                array_values($overrides['speakers'] ?? []),
                $shipped,
                $overrides['speakers_removed'] ?? [],
            ),
            is_array($base['speakersWithdrawn'] ?? null) ? $base['speakersWithdrawn'] : [],
        ),
        $shipped,
        $config,
    );
    unset($base['speakersWithdrawn']);
    return $base;
}

/**
 * Speakers the organiser has taken off the bill.
 *
 * The one thing a deploy may remove from the operator's list, and only
 * because leaving it is worse: on 20 November 2026 Indonesia replaced Maya
 * Watono on the opening panel, and her card — name, title and face — would
 * otherwise have gone on standing on the live page until somebody thought
 * to open the admin and delete it. The build names who is no longer coming;
 * nothing else about the list is touched, and a name added here stays out
 * however the operator reorders around it.
 *
 * Deliberately not the inverse of site_speakers_with_new_arrivals(): a
 * speaker the build simply stops shipping is *not* removed, because the
 * build stops shipping people for all sorts of reasons and none of them is
 * "they withdrew". It has to be said in so many words.
 */
function site_speakers_without_withdrawn(array $list, array $withdrawn): array
{
    if (!$withdrawn) {
        return $list;
    }
    $out = [];
    $gone = array_flip(array_map('strval', $withdrawn));
    foreach ($list as $one) {
        $id = is_array($one) ? (string) ($one['id'] ?? '') : '';
        if ($id !== '' && isset($gone[$id])) {
            continue;
        }
        $out[] = $one;
    }
    return $out;
}

/**
 * Speakers who have arrived since the operator took the list over.
 *
 * The overlay owns the list — nothing a deploy does may undo an evening in
 * the admin. But the organiser goes on sending names: eighteen arrived on
 * 19 November 2026 alone, and without this they would have sat in the
 * build while the site showed the list as it was the week before, with the
 * client reading it back as "eighteen people missing".
 *
 * So a speaker the build ships whose id is in neither the operator's list
 * nor their record of removals is appended to the end of it. Removals are
 * remembered precisely so this cannot hand back somebody taken off on
 * purpose, and the operator's order, wording and portraits are untouched;
 * a new name simply arrives last, where they can move it.
 */
function site_speakers_with_new_arrivals(array $list, array $shipped, array $removed): array
{
    if (!$shipped) {
        return $list;
    }
    $have = [];
    foreach ($list as $one) {
        $id = is_array($one) ? (string) ($one['id'] ?? '') : '';
        if ($id !== '') {
            $have[$id] = true;
        }
    }
    foreach ($removed as $id) {
        $have[(string) $id] = true;
    }
    foreach ($shipped as $one) {
        if (!is_array($one)) {
            continue;
        }
        $id = (string) ($one['id'] ?? '');
        if ($id === '' || isset($have[$id])) {
            continue;
        }
        $list[] = $one;
        $have[$id] = true;
    }
    return $list;
}

/**
 * Portraits that arrive with a deploy, for people who have none.
 *
 * The operator's list stands over the build's — that is what the overlay is
 * for, and a deploy must never undo an evening's work in the admin. But
 * "this speaker has no portrait yet" is a gap, not an arrangement: the
 * organiser keeps sending photographs, they are prepared and shipped with
 * the site, and without this they would sit in the site's own photo folder
 * while the card beside them went on drawing a figure.
 *
 * So a speaker the operator's list carries **without** a portrait is given
 * the one the build ships for them. Nothing else is touched: their name,
 * their title, their place in the order and a portrait uploaded from the
 * admin all stay exactly as they are.
 *
 * Matched by id first. Then, only where both sides are unambiguous, by
 * name: a speaker removed in the admin and typed in again gets a new id,
 * and the photograph the build carries for them is unmistakably theirs. A
 * name that appears twice on either side is left alone rather than guessed
 * at.
 *
 * A portrait the operator has taken off is not put back: «Rasmni olib
 * tashlash» writes `photoOff` on the speaker, and that is the one thing
 * here that says "no portrait" deliberately. Uploading one clears it.
 *
 * With `$config`, a portrait a browser cannot actually fetch counts as no
 * portrait. That is for one case and it is not theoretical: three portraits
 * uploaded from the admin were not being put back into the web root after a
 * deploy, so the cards pointed at pictures that answered 404, and the
 * organiser sent the same three photographs to be shipped with the site
 * instead. Without this they would have lost to the broken reference.
 *
 * The test is the web root and nothing else, because the web root is what
 * the page fetches from — which is why site_publish() puts the uploaded
 * photographs back *before* it merges rather than after. Passed by
 * site_publish() and by nothing else: the admin's own list has to go on
 * showing what it actually holds, so that a missing picture reads as a
 * missing picture where somebody can do something about it.
 */
function site_speakers_with_shipped_photos(
    array $list,
    array $shipped,
    ?array $config = null,
): array {
    if (!$list || !$shipped) {
        return $list;
    }
    if ($config !== null) {
        require_once __DIR__ . '/photos.php';
    }


    $byId = [];
    /** @var array<string,string|false> $byName — false once a name repeats. */
    $byName = [];
    foreach ($shipped as $one) {
        $photo = is_array($one) ? ($one['photo'] ?? null) : null;
        if (!is_string($photo) || $photo === '') {
            continue;
        }
        $id = (string) ($one['id'] ?? '');
        if ($id !== '') {
            $byId[$id] = $photo;
        }
        foreach (site_speaker_names($one) as $name) {
            $byName[$name] = array_key_exists($name, $byName) ? false : $photo;
        }
    }

    /* A name the operator's own list uses twice is ambiguous from this side
       too, and is taken out of the running before anything is filled in. */
    $seen = [];
    foreach ($list as $one) {
        foreach (site_speaker_names(is_array($one) ? $one : []) as $name) {
            $seen[$name] = ($seen[$name] ?? 0) + 1;
        }
    }

    foreach ($list as $i => $one) {
        if (!is_array($one)) {
            continue;
        }
        $photo = $one['photo'] ?? null;
        if (is_string($photo) && $photo !== '') {
            if ($config === null || photos_served($config, $photo)) {
                continue;
            }
            /* The reference is to a picture this installation does not
               have. Fall through and let the build's own portrait stand in
               for it; the operator's record is not touched. */
        }
        if (!empty($one['photoOff'])) {
            continue;
        }
        $id = (string) ($one['id'] ?? '');
        if ($id !== '' && isset($byId[$id])) {
            $list[$i]['photo'] = $byId[$id];
            continue;
        }
        foreach (site_speaker_names($one) as $name) {
            if (($seen[$name] ?? 0) === 1 && !empty($byName[$name])) {
                $list[$i]['photo'] = $byName[$name];
                break;
            }
        }
    }

    return $list;
}

/**
 * The names a speaker can be recognised by, folded for comparison.
 *
 * Case and the spacing around it only; nothing is transliterated. Two
 * people whose names differ by an apostrophe are two people as far as this
 * is concerned, which is the safe way round.
 */
function site_speaker_names(array $speaker): array
{
    $names = [];
    foreach (['uz', 'ru', 'en'] as $lang) {
        $value = $speaker['name'][$lang] ?? null;
        if (!is_string($value)) {
            continue;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            continue;
        }
        $names[] = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
    return array_values(array_unique($names));
}

/**
 * Make sure the file has somewhere to put the title card's pictures.
 *
 * `media.hero` is an ordinary override — a path with a value — and site_set()
 * writes only into paths that already exist, on purpose. A server running a
 * build from before this field existed would therefore refuse the upload and
 * list it as stale. One empty list costs nothing and means the admin works
 * against the site that is installed rather than the one in the repository.
 */
function site_with_media(array $base, string $key): array
{
    if ($key !== 'forum') {
        return $base;
    }
    if (!isset($base['media']) || !is_array($base['media'])) {
        $base['media'] = ['hero' => []];
        return $base;
    }
    $base['media'] += ['hero' => []];
    return $base;
}

/**
 * The speaker list the admin works on.
 *
 * The operator's own arrangement once they have made one; until then, the
 * list the build shipped — so the speakers page shows what the site is
 * showing rather than an empty page beside a chapter full of faces. The
 * first save copies whatever is here into the overlay, and from that moment
 * it is theirs.
 */
function site_speakers(array $config): array
{
    $base = site_base($config, 'forum');
    $shipped = is_array($base) && is_array($base['speakers'] ?? null) ? $base['speakers'] : [];

    $overrides = site_overrides($config, 'forum');
    if ($overrides['speakers_set'] ?? false) {
        /* The same arrivals and the same filling-in the site gets, so the
           page the operator edits shows the page they publish. */
        return site_speakers_with_shipped_photos(
            site_speakers_with_new_arrivals(
                array_values($overrides['speakers'] ?? []),
                $shipped,
                $overrides['speakers_removed'] ?? [],
            ),
            $shipped,
        );
    }
    return array_values($shipped);
}

/** Put the whole list back, in the order it is given. */
function site_save_speakers(array $config, array $list): void
{
    $overrides = site_overrides($config, 'forum');
    $overrides['speakers'] = array_values($list);
    /* From here on the list is theirs, empty or not. */
    $overrides['speakers_set'] = true;
    site_save_overrides($config, 'forum', $overrides);
}

/** base + added + speakers + overrides, as the site will read it. */
function site_merged(
    array $base,
    array $overrides,
    string $key,
    array &$stale = [],
    ?array $config = null,
): array {
    $data = site_with_media(
        site_with_speakers(site_with_added($base, $overrides, $key), $overrides, $key, $config),
        $key,
    );

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

    /*
     * The photographs, back into the web root — before the merge, not after.
     *
     * A deploy copies the build over the live site with `rsync --delete`, so
     * every photograph uploaded from the admin is removed from the web root
     * whenever one lands. The deploy's last step is to publish, which is
     * here, so they are put back before it finishes.
     *
     * First, because the merge asks whether a speaker's portrait can be
     * fetched at all and answers that by looking in the web root. Done after
     * the merge, the answer would be "no" for every upload on every deploy.
     * Done here, "no" means what it should: this installation cannot serve
     * that picture, for whatever reason, and whatever the build ships for
     * that person is better than a frame that 404s.
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

    $stale = [];
    $merged = site_merged($base, $overrides, $key, $stale, $config);

    $published = site_published_file($config, $key);
    site_write_json($published, $merged);
    clearstatcache(true, $published);

    $overrides['published_sha'] = (string) sha1_file($published);
    site_write_json(site_state_file($config, $key, 'overrides'), $overrides);

    return [
        'applied' => count($overrides['values']),
        'hidden' => count($overrides['hidden']),
        'added' => count($overrides['added']),
        'photos' => $photos['copied'],
        'photos_failed' => $photos['failed'],
        'photos_blocked' => $photos['blocked'] ?? false,
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
