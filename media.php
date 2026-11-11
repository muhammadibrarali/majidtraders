<?php
/**
 * Serves Media Library files from private/uploads to visitors.
 *
 *   /media/12/site-visit.webp      large image
 *   /media/12/md/site-visit.webp   medium image
 *   /media/12/sm/site-visit.webp   thumbnail
 *   /media/15/company-profile.pdf  PDF (opens in the browser)
 *
 * Files never change once uploaded (a new upload gets a new number), so
 * browsers may cache them for a year.
 */
declare(strict_types=1);

define('MT_NO_SESSION', true); // no cookies, so browsers/CDN can cache
require __DIR__ . '/includes/bootstrap.php';
require ROOT_PATH . '/includes/media_lib.php';

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
if (!preg_match('#^/media/(\d{1,9})/(?:(sm|md)/)?([a-z0-9-]{1,100})\.(webp|jpg|pdf)$#', $path, $m)) {
    show_error_page(404);
}
[, $id, $size, $slug, $ext] = $m;
$size = $size !== '' ? $size : 'lg';

$media = media_find((int) $id);
if (!$media || ($media['kind'] === 'document' && $size !== 'lg') || media_ext($media) !== $ext) {
    show_error_page(404);
}

// Old or mistyped name -> send to the correct address.
$canonical = media_url($media, $size);
if ($canonical !== $path) {
    header('Location: ' . $canonical, true, 301);
    exit;
}

$file = media_path($media, $size);
if (!is_file($file) || !is_readable($file)) {
    error_log('[majidtraders] Media file missing: ' . $file);
    show_error_page(404);
}

$mtime  = (int) filemtime($file);
$length = (int) filesize($file);
$etag   = '"' . $media['id'] . '-' . $size . '-' . $mtime . '-' . $length . '"';

header_remove('Pragma');
header_remove('Set-Cookie');
header('Cache-Control: public, max-age=31536000, immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

if ($media['kind'] === 'document') {
    // PDFs: open in the browser's built-in viewer (a strict policy can block it).
    header('Content-Security-Policy: frame-ancestors \'self\'');
    $download = slugify((string) ($media['title'] ?: 'document'), 80) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $download . '"');
} else {
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'self'");
    header('Content-Type: ' . $media['mime_type']);
}

$ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$ifModified  = (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
if (($ifNoneMatch !== '' && $ifNoneMatch === $etag)
    || ($ifNoneMatch === '' && $ifModified !== '' && strtotime($ifModified) >= $mtime)) {
    http_response_code(304);
    exit;
}

header('Content-Length: ' . $length);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    exit;
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($file);
