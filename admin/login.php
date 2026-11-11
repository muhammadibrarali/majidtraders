<?php
/** Admin login — /admin/login */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
admin_headers();

// Already logged in? Go straight to the dashboard.
if (current_admin() !== null) {
    redirect('/admin/');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_check()) {
        $error = 'Your session expired. Please try again.';
    } elseif (attempt_login($email, $password)) {
        redirect('/admin/');
    } else {
        $error = LOGIN_GENERIC_ERROR;
    }
}
$flashes = take_flashes();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin login – Majid Traders</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth">
<main class="auth__card">
  <div class="auth__logo" aria-hidden="true">MT</div>
  <h1>Admin login</h1>
  <p class="muted">Majid Traders website administration</p>

  <?php foreach ($flashes as $f): ?>
    <div class="notice notice--<?= $f['type'] === 'success' ? 'ok' : 'info' ?>" role="status"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
  <?php if ($error !== ''): ?>
    <div class="notice notice--error" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/admin/login" class="form">
    <?= csrf_field() ?>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?= e($email) ?>" autofocus>

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required maxlength="200" autocomplete="current-password">

    <button type="submit" class="btn btn--block">Log in</button>
  </form>

  <p class="auth__foot"><a href="/">← Back to website</a></p>
</main>
</body>
</html>
