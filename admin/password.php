<?php
/** Change password — /admin/password */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
require ROOT_PATH . '/includes/admin_layout.php';

$admin  = require_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $stmt->execute([$admin['id']]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            // Wrong current password counts as a failed login (same 5 tries / 15 minutes rule).
            record_login_attempt($admin['email'], client_ip(), false);
            if (login_is_locked($admin['email'], client_ip())) {
                logout_admin();
                session_start();
                flash('info', 'Too many wrong passwords. You have been logged out for safety.');
                redirect('/admin/login');
            }
            $errors[] = 'Your current password is not correct.';
        } else {
            $errors = password_problems($new, $confirm, $admin['email']);
            if (!$errors && password_verify($new, $hash)) {
                $errors[] = 'The new password must be different from the current one.';
            }
        }

        if (!$errors) {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([$newHash, $admin['id']]);

            // Keep THIS session logged in; every other session no longer matches and is logged out.
            session_regenerate_id(true);
            $_SESSION['admin_fp'] = auth_fingerprint($newHash);
            unset($_SESSION['csrf_token']);
            flash('success', 'Password changed. All other devices have been logged out.');
            redirect('/admin/password');
        }
    }
}

admin_page_start('Change Password', 'password', $admin);
?>
  <section class="card card--narrow">
    <?php if ($errors): ?>
      <div class="notice notice--error" role="alert">
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
    <form method="post" action="/admin/password" class="form">
      <?= csrf_field() ?>
      <input type="text" name="username" value="<?= e($admin['email']) ?>" autocomplete="username" hidden>

      <label for="current_password">Current password</label>
      <input id="current_password" name="current_password" type="password" required autocomplete="current-password">

      <label for="new_password">New password <span class="hint">(at least 12 characters, with letters and numbers)</span></label>
      <input id="new_password" name="new_password" type="password" required minlength="12" maxlength="72" autocomplete="new-password">

      <label for="new_password_confirm">Repeat new password</label>
      <input id="new_password_confirm" name="new_password_confirm" type="password" required minlength="12" maxlength="72" autocomplete="new-password">

      <button type="submit" class="btn">Change password</button>
    </form>
    <p class="muted small">After changing, you stay logged in here, and every other device is logged out.</p>
  </section>
<?php
admin_page_end();
