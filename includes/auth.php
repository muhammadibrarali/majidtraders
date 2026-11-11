<?php
/**
 * Admin login, lockout, idle timeout and page protection.
 *
 * Every admin page starts with:
 *     require dirname(__DIR__) . '/includes/auth.php';
 *     $admin = require_admin();      // (login page uses admin_headers() only)
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const ADMIN_IDLE_TIMEOUT   = 30 * 60; // log out after 30 minutes of no activity
const LOGIN_MAX_ATTEMPTS   = 5;       // wrong attempts allowed ...
const LOGIN_LOCK_MINUTES   = 15;      // ... within / locked for this many minutes
// A real hash of a random value: used so unknown emails take as long to check as real ones.
const LOGIN_DUMMY_HASH     = '$2y$10$sVEhKPQBWvvMnpTOQxNOMeymKW7.hU/cuk8YXRu2upv3.gUTLawKa';
const LOGIN_GENERIC_ERROR  = 'Login failed. Please check your email and password. After 5 failed attempts, login is blocked for 15 minutes.';

/** Headers every admin page sends: never indexed, never cached. */
function admin_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-LiteSpeed-Cache-Control: no-cache');
}

/**
 * Fingerprint of the admin's password hash. Stored in the session; when the
 * password changes, every other session's fingerprint no longer matches,
 * so those sessions are logged out automatically.
 */
function auth_fingerprint(string $passwordHash): string
{
    return hash_hmac('sha256', $passwordHash, (string) config('security.app_key'));
}

/** Short-lived message shown once on the next page ("Password changed" etc.). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

// ---------------------------------------------------------------------------
// Lockout (uses the login_attempts table)
// ---------------------------------------------------------------------------

/** True if this email OR this IP had 5+ failed attempts in the last 15 minutes. */
function login_is_locked(string $email, string $ip): bool
{
    $stmt = db()->prepare(
        'SELECT
            SUM(email = :email) AS by_email,
            SUM(ip_address = :ip) AS by_ip
         FROM login_attempts
         WHERE success = 0
           AND attempted_at > (NOW() - INTERVAL ' . LOGIN_LOCK_MINUTES . ' MINUTE)
           AND (email = :email2 OR ip_address = :ip2)'
    );
    $stmt->execute([':email' => $email, ':ip' => $ip, ':email2' => $email, ':ip2' => $ip]);
    $row = $stmt->fetch() ?: [];
    return (int) ($row['by_email'] ?? 0) >= LOGIN_MAX_ATTEMPTS
        || (int) ($row['by_ip'] ?? 0) >= LOGIN_MAX_ATTEMPTS;
}

function record_login_attempt(string $email, string $ip, bool $success): void
{
    db()->prepare(
        'INSERT INTO login_attempts (email, ip_address, user_agent, success) VALUES (?, ?, ?, ?)'
    )->execute([
        mb_substr($email, 0, 190),
        $ip,
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        $success ? 1 : 0,
    ]);

    // On success, clear this email's failed attempts so the counter starts fresh.
    if ($success) {
        db()->prepare('DELETE FROM login_attempts WHERE email = ? AND success = 0')->execute([$email]);
    }
    // Housekeeping: now and then remove records older than 90 days.
    if (random_int(1, 50) === 1) {
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 90 DAY)');
    }
}

// ---------------------------------------------------------------------------
// Login / logout
// ---------------------------------------------------------------------------

/**
 * Try to log in. Returns true on success. On failure, the caller always shows
 * the same generic error (we never reveal whether the email exists or is locked).
 */
function attempt_login(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    $ip    = client_ip();

    if ($email === '' || $password === '' || strlen($password) > 1024) {
        return false;
    }
    if (login_is_locked($email, $ip)) {
        // Still run a hash check so a locked login takes the same time as a normal one.
        password_verify($password, LOGIN_DUMMY_HASH);
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always verify against *some* hash so timing doesn't reveal unknown emails.
    $hash  = $user['password_hash'] ?? LOGIN_DUMMY_HASH;
    $valid = password_verify($password, $hash) && $user && (int) $user['is_active'] === 1;

    record_login_attempt($email, $ip, $valid);
    if (!$valid) {
        return false;
    }

    // Upgrade the stored hash if PHP's default algorithm got stronger.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
            ->execute([$user['password_hash'], $user['id']]);
    }

    // New session ID: protects against session-fixation attacks.
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['admin_id']          = (int) $user['id'];
    $_SESSION['admin_fp']          = auth_fingerprint($user['password_hash']);
    $_SESSION['admin_last_active'] = time();
    $_SESSION['admin_prev_login']  = $user['last_login_at'];   // shown on the dashboard
    $_SESSION['admin_prev_ip']     = $user['last_login_ip'];

    db()->prepare('UPDATE admin_users SET last_login_at = NOW(), last_login_ip = ?, failed_logins = 0, locked_until = NULL WHERE id = ?')
        ->execute([$ip, $user['id']]);

    return true;
}

/** Fully end the current session (server side and the browser cookie). */
function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** The logged-in admin, or null. Also enforces the idle timeout and password-change logout. */
function current_admin(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $cached = null;

    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    // Idle timeout
    if (time() - (int) ($_SESSION['admin_last_active'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
        logout_admin();
        session_start();
        flash('info', 'You were logged out after 30 minutes of inactivity. Please log in again.');
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role, password_hash, is_active, last_login_at, last_login_ip FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['admin_id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1
        || !hash_equals((string) ($_SESSION['admin_fp'] ?? ''), auth_fingerprint($user['password_hash']))) {
        // Account removed/disabled or password changed elsewhere.
        logout_admin();
        session_start();
        flash('info', 'Your session has ended. Please log in again.');
        return null;
    }

    $_SESSION['admin_last_active'] = time();
    unset($user['password_hash']);
    return $cached = $user;
}

/** Did the browser's script ask for a JSON answer (media picker, uploads)? */
function wants_json(): bool
{
    return ($_GET['format'] ?? $_POST['format'] ?? '') === 'json'
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

/** Send a JSON answer and stop. */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Use at the top of every protected admin page. */
function require_admin(): array
{
    admin_headers();
    $admin = current_admin();
    if ($admin === null) {
        if (wants_json()) {
            json_response(['ok' => false, 'error' => 'Your session has ended. Please reload the page and log in again.'], 401);
        }
        redirect('/admin/login');
    }
    // Apply any pending small database upgrades (runs once per upgrade).
    require_once __DIR__ . '/migrations.php';
    run_migrations();
    return $admin;
}

/** Shared password rules. Returns a list of problems (empty = OK). */
function password_problems(string $password, string $confirm, string $email = ''): array
{
    $errors = [];
    if (mb_strlen($password) < 12) {
        $errors[] = 'The new password must be at least 12 characters long.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'The new password is too long (maximum 72 characters).';
    } elseif (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'The new password must contain both letters and numbers.';
    } else {
        $local = (string) strstr($email, '@', true);
        if (strlen($local) >= 4 && stripos($password, $local) !== false) {
            $errors[] = 'The new password must not contain your email name.';
        }
    }
    if ($password !== $confirm) {
        $errors[] = 'The two new passwords do not match.';
    }
    return $errors;
}
