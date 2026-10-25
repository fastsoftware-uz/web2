<?php
/**
 * The nginx block from README.md, as a router for PHP's built-in server.
 *
 *   npm run server          # build site 2, then serve the whole thing
 *
 * It exists so the registration chain can be driven end to end on one
 * machine — form, endpoint, store, admin, CSV — before any of it is copied
 * to the organiser's server. The real deployment uses nginx and the config
 * in README.md; this file is never part of it, and `php -S` is single
 * threaded and says so in its own documentation.
 *
 * The document root is the built site (`site-2-silkroad/dist`), passed with
 * `-t`. Everything this router does not claim is answered from there, which
 * is exactly how the nginx block is written.
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$public = __DIR__ . '/public';

/* The one public endpoint, and its health check. */
if ($path === '/api/register' || $path === '/api/health') {
    require $public . '/api.php';
    return true;
}

/* The e-ticket. The number is in the path so that the address reads as one
   thing in a mail and on a printed badge. */
if (preg_match('#^/ticket(/|$)#', $path) === 1) {
    require $public . '/ticket.php';
    return true;
}

/* The admin. `/admin` without the slash would make every relative link on
   the page resolve one level too high, so it is redirected rather than
   served — the same thing nginx's `index` directive does. */
if ($path === '/admin') {
    header('Location: /admin/' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
    return true;
}
if ($path === '/admin/') {
    require $public . '/index.php';
    return true;
}
if (preg_match('#^/admin/([A-Za-z0-9._-]+)$#', $path, $m) === 1) {
    $file = $public . '/' . $m[1];
    if ($m[1] !== 'api.php' && is_file($file)) {
        if (str_ends_with($file, '.php')) {
            require $file;
        } else {
            // The built-in server only guesses content types for files under
            // its own document root, and admin.css is not under it.
            header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/octet-stream'));
            readfile($file);
        }
        return true;
    }
}

/* Anything else is the built site: let the server answer it from -t. */
return false;
