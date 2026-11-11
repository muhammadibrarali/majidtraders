<?php
/**
 * Shared start-up file. Every page that needs the database includes this first.
 *
 *   1. Hides PHP errors from visitors (they are written to the error log).
 *   2. Loads ../private/config.php — if it is missing or incomplete, the
 *      friendly "being set up" page is shown instead of a PHP error.
 *   3. Starts a secure session.
 *   4. Provides db(): a PDO MySQL connection (utf8mb4, exceptions, real prepares).
 *   5. Provides CSRF helpers for forms.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Any uncaught error -> log it, then show the friendly 500 page.
set_exception_handler(static function (Throwable $ex): void {
    error_log('[majidtraders] ' . $ex);
    show_error_page(500);
});

// ---------------------------------------------------------------------------
// 1) Configuration
// ---------------------------------------------------------------------------
(static function (): void {
    $file = PRIVATE_PATH . '/config.php';
    $cfg  = is_file($file) && is_readable($file) ? (include $file) : null;

    $ok = is_array($cfg)
        && !empty($cfg['db']['name']) && !empty($cfg['db']['user'])
        && isset($cfg['db']['password'])
        && !empty($cfg['security']['app_key'])
        && strlen((string) $cfg['security']['app_key']) >= 32
        && !str_contains((string) $cfg['security']['app_key'], 'CHANGE_ME');

    if (!$ok) {
        error_log('[majidtraders] private/config.php is missing or incomplete (expected at ' . $file . ')');
        show_error_page(503);
    }
    $GLOBALS['__mt_config'] = $cfg;
})();

/** Read a config value with dot notation, e.g. config('site.url'). */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__mt_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set((string) config('site.timezone', 'Asia/Karachi'));

// ---------------------------------------------------------------------------
// 2) Secure session
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    // Keep session files for 1 hour (our own 30-minute idle timeout is enforced in auth.php).
    ini_set('session.gc_maxlifetime', '3600');
    // Store sessions in private/sessions (outside public_html) when possible.
    $sessionDir = PRIVATE_PATH . '/sessions';
    if (is_dir($sessionDir) || @mkdir($sessionDir, 0700, true)) {
        if (is_writable($sessionDir)) {
            session_save_path($sessionDir);
            // Make PHP clean up old session files in this folder itself.
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
    }
    session_name('mt_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Give the session a new ID (call after login / privilege change). */
function session_regenerate(): void
{
    session_regenerate_id(true);
}

// ---------------------------------------------------------------------------
// 3) Database
// ---------------------------------------------------------------------------
/** Shared PDO connection. Shows the friendly error page if the DB is unreachable. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        config('db.host', 'localhost'),
        (int) config('db.port', 3306),
        config('db.name')
    );
    try {
        $pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.password'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    } catch (PDOException $ex) {
        // Never reveal the real message (it can contain the username/host).
        error_log('[majidtraders] Database connection failed: ' . $ex->getMessage());
        show_error_page(503);
    }
    return $pdo;
}

// ---------------------------------------------------------------------------
// 4) CSRF protection
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden form field containing the CSRF token. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** True if the submitted CSRF token matches the session token. */
function csrf_check(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/** Client IP address (for logging and login lockout). */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
