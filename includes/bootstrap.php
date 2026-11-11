<?php
/**
 * Shared start-up file. Every page includes this first.
 * - Hides PHP errors from visitors (they are logged instead).
 * - Loads the private config from ../private/config.php if it exists.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

define('ROOT_PATH', dirname(__DIR__));
define('PRIVATE_PATH', dirname(ROOT_PATH) . '/private');

/** Read a config value, e.g. config('site.url'). Returns $default if missing. */
function config(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $file = PRIVATE_PATH . '/config.php';
        $cfg = is_file($file) ? (array) require $file : [];
    }
    $value = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escape text for safe HTML output. */
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Turn any uncaught error into the friendly 500 page. */
set_exception_handler(function (Throwable $ex): void {
    error_log((string) $ex);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $_GET['code'] = '500';
    require ROOT_PATH . '/error.php';
    exit;
});
