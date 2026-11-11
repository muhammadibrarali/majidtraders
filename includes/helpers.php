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
