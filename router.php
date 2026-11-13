<?php
/**
 * Clean-URL router.
 *
 * .htaccess sends every address that is not a real file or folder here.
 * This file maps the address to the right PHP page:
 *
 *   /about              -> about.php
 *   /admin/services     -> admin/services.php
 *   /admin/services/    -> admin/services.php  (trailing slash allowed)
 *
 * Anything else shows the friendly 404 page. Only simple lower-case names
 * are accepted, and only in the website root or /admin, so this can never
 * open files in /includes, /lib or outside the website.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Pages that must never be opened through a clean address.
$blocked = ['router', 'error', 'index', 'config.example'];

if (preg_match('#^/(?:(admin)/)?([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $m)
    && !in_array($m[2], $blocked, true)) {
    $file = ROOT_PATH . '/' . ($m[1] !== '' ? $m[1] . '/' : '') . $m[2] . '.php';
    if (is_file($file)) {
        chdir(dirname($file));
        require $file;
        return;
    }
}

// Media Library files: /media/12/name.webp, /media/12/sm/name.webp, /media/15/name.pdf
if (preg_match('#^/media/\d+/#', $path)) {
    require ROOT_PATH . '/media.php';
    return;
}

// Detail pages (e.g. /projects/school-renovation-mardan) will be added here
// when those pages are built.

// A missing static file (CSS, JS, font, image…) must NOT receive the HTML
// "Page not found" page: browsers refuse HTML as CSS/JS ("nosniff"), and a
// cache could keep it. Answer with a short plain-text 404 that is never cached.
if (preg_match('#\.(css|js|mjs|map|woff2?|ttf|otf|svg|png|jpe?g|gif|webp|avif|ico|json)$#i', $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Not found';
    return;
}

show_error_page(404);
