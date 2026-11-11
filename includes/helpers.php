<?php
/**
 * Small helper functions that need NO database and NO config.
 * Safe to load from any page, including the error page.
 */
declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
    define('PRIVATE_PATH', dirname(ROOT_PATH) . '/private');
}

/** Escape text for safe HTML output. */
function e(mixed $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Is the current request served over HTTPS (also behind Hostinger's proxy)? */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Send the browser to another page on this site and stop. */
function redirect(string $path): never
{
    // Only allow local paths like "/admin/" (never another website).
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        $path = '/';
    }
    header('Location: ' . $path, true, 303);
    exit;
}

/**
 * Show the friendly error page with the given HTTP code and stop.
 * Visitors never see technical details; those go to the error log.
 */
function show_error_page(int $code): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
        if ($code === 503) {
            header('Retry-After: 600');
        }
    }
    $GLOBALS['error_code'] = $code;
    require ROOT_PATH . '/error.php';
    exit;
}

/**
 * Turn any text into a clean web-address part:
 * "Import & Export (Mardan)" -> "import-and-export-mardan"
 */
function slugify(string $text, int $maxLength = 160): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        if (is_string($t)) {
            $text = $t;
        }
    } elseif (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($t)) {
            $text = $t;
        }
    }
    $text = strtolower($text);
    $text = str_replace(['&', '@'], [' and ', ' at '], $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if (strlen($text) > $maxLength) {
        $text = rtrim(substr($text, 0, $maxLength), '-');
    }
    return $text;
}

// ---------------------------------------------------------------------------
// Content-Security-Policy (sent by PHP so special responses, such as PDFs,
// can use a different policy). Other security headers live in .htaccess.
// Inline <script>/<style> and style="" attributes are NOT allowed.
// ---------------------------------------------------------------------------
const SITE_CSP = "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self'; "
    . "img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src https://www.google.com; "
    . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; upgrade-insecure-requests";

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Security-Policy: ' . SITE_CSP);
}
