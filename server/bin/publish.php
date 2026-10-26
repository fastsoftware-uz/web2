<?php
/**
 * Put the organiser's edits back on top of a freshly deployed site.
 *
 *     php server/bin/publish.php
 *
 * The admin keeps what was changed as an overlay — a list of paths and their
 * new values — and publishes `base + overlay` into the site's own data/*.json
 * (see src/site.php). A deploy replaces those files with the build output,
 * which is the base again: correct, and also the moment every edit the
 * organiser has made disappears from the live site until somebody opens the
 * admin and presses Nashr.
 *
 * So the deploy presses it. This is the last step of the deploy workflow, run
 * over SSH after the files land. Nothing to decide and nothing to lose: the
 * base is re-read because its hash changed, and the overlay is applied to it.
 *
 * Exit code 0 when both files were written, 1 when one was not — the deploy
 * should fail loudly rather than leave the site saying last month's dates.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/app.php';
require_once __DIR__ . '/../src/site.php';

$config = app_config();
$failed = 0;

foreach (SITE_FILES as $key => $meta) {
    try {
        $result = site_publish($config, $key);
        $note = $result['base'] === 'redeployed' ? ' (yangi build aniqlandi)' : '';
        echo sprintf(
            "%s: %d ta o'zgartirish qo'llandi%s -> %s\n",
            $meta['file'],
            (int) $result['applied'],
            $note,
            $result['file'],
        );
        if ($result['stale'] !== []) {
            // A path the organiser edited that the new build no longer has.
            // Not fatal — the rest is published — but somebody should know.
            echo '  eslatma: yo\'qolgan maydonlar: ' . implode(', ', array_slice($result['stale'], 0, 8)) . "\n";
        }
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, $meta['file'] . ': ' . $e->getMessage() . "\n");
    }
}

exit($failed === 0 ? 0 : 1);
