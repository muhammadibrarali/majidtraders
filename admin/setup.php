<?php
/**
 * ONE-TIME SETUP — /admin/setup
 *
 *  - Creates all database tables (if they don't exist yet).
 *  - Lets the owner create the FIRST admin account.
 *  - As soon as an admin exists, it permanently refuses to run.
 *
 * Extra protection: the form asks for the secret key from private/config.php,
 * so only someone with access to the hosting account can complete setup.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT_PATH . '/includes/install.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

$pdo = db();

/** Render the page and stop. */
function render_setup(string $view, array $data = []): never
{
    extract($data, EXTR_SKIP);
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Website setup – Majid Traders</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth">
<main class="auth__card">
  <div class="auth__logo" aria-hidden="true">MT</div>
<?php if ($view === 'completed'): ?>
  <h1>Setup already completed</h1>
  <p class="muted">An admin account already exists, so this page is permanently switched off.</p>
<?php elseif ($view === 'success'): ?>
  <h1>Setup complete</h1>
  <p class="notice notice--ok">Your admin account <strong><?= e($email) ?></strong> has been created and all <?= (int) $tableCount ?> database tables are ready.</p>
  <p class="muted">The admin login page will be added in the next step. This setup page is now permanently switched off.</p>
<?php else: ?>
  <h1>Website setup</h1>
  <p class="notice notice--ok">Database connected. All <?= (int) $tableCount ?> tables are ready.</p>
  <p class="muted">Create the first admin account. You will use it to log in to the admin panel.</p>

  <?php if (!empty($errors)): ?>
    <div class="notice notice--error" role="alert">
      <p><strong>Please fix the following:</strong></p>
      <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" action="/admin/setup" class="form" novalidate>
    <?= csrf_field() ?>
    <label for="name">Full name</label>
    <input id="name" name="name" type="text" required maxlength="100" autocomplete="name" value="<?= e($old['name'] ?? '') ?>">

    <label for="email">Email address</label>
    <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($old['email'] ?? '') ?>">

    <label for="password">Password <span class="hint">(at least 12 characters, with letters and numbers)</span></label>
    <input id="password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password">

    <label for="password_confirm">Repeat password</label>
    <input id="password_confirm" name="password_confirm" type="password" required minlength="12" maxlength="72" autocomplete="new-password">

    <label for="setup_key">Setup key <span class="hint">(the <code>app_key</code> value from your private/config.php)</span></label>
    <input id="setup_key" name="setup_key" type="password" required autocomplete="off">

    <button type="submit" class="btn">Create admin account</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
<?php
    exit;
}

// ---------------------------------------------------------------------------
// 1) Already done? Then refuse permanently.
// ---------------------------------------------------------------------------
if (setup_is_completed($pdo)) {
    http_response_code(403);
    render_setup('completed');
}

// ---------------------------------------------------------------------------
// 2) Create tables (safe to repeat) and default settings.
// ---------------------------------------------------------------------------
$tables = install_schema($pdo);
seed_defaults($pdo);
$tableCount = count($tables);

// ---------------------------------------------------------------------------
// 3) Handle the form.
// ---------------------------------------------------------------------------
$errors = [];
$old    = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');
    $setupKey = trim((string) ($_POST['setup_key'] ?? ''));
    $old      = ['name' => $name, 'email' => $email];

    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    } else {
        if (!hash_equals((string) config('security.app_key'), $setupKey)) {
            $errors[] = 'The setup key does not match the app_key in private/config.php.';
            error_log('[majidtraders] Wrong setup key from ' . client_ip());
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Name must be between 2 and 100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters long.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'Password is too long (maximum 72 characters).';
        } elseif (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain both letters and numbers.';
        } elseif (strlen((string) strstr($email, '@', true)) >= 4
            && stripos($password, (string) strstr($email, '@', true)) !== false) {
            $errors[] = 'Password must not contain your email name.';
        }
        if ($password !== $confirm) {
            $errors[] = 'The two passwords do not match.';
        }
    }

    if (!$errors) {
        // Lock so two people submitting at the same moment can't both succeed.
        $pdo->query("SELECT GET_LOCK('mt_setup', 10)");
        try {
            if (setup_is_completed($pdo)) {
                http_response_code(403);
                render_setup('completed');
            }
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO admin_users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, 'admin', 1)"
            )->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')
                ->execute([date('Y-m-d H:i:s'), 'setup_completed_at']);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('mt_setup')");
        }

        session_regenerate();
        unset($_SESSION['csrf_token']);
        render_setup('success', ['email' => $email, 'tableCount' => $tableCount]);
    }
}

render_setup('form', ['errors' => $errors, 'old' => $old, 'tableCount' => $tableCount]);
