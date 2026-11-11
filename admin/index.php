<?php
/** Admin dashboard — /admin/ */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
require ROOT_PATH . '/includes/admin_layout.php';

$admin = require_admin();
$pdo   = db();

/** Count rows; returns [total, published]. */
$count = static function (string $table) use ($pdo): array {
    $row = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'published'), 0) AS published FROM `$table`")->fetch();
    return [(int) $row['total'], (int) $row['published']];
};

$stats = [
    ['Projects',   'building',  '/admin/projects', ...$count('projects')],
    ['Clients',    'handshake', '/admin/clients',  ...$count('clients')],
    ['Team',       'users',     '/admin/team',     ...$count('team_members')],
    ['Blog posts', 'pen',       '/admin/blog',     ...$count('blog_posts')],
];
$unread = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();

$prevLogin = $_SESSION['admin_prev_login'] ?? null;
$prevIp    = $_SESSION['admin_prev_ip'] ?? null;

admin_page_start('Dashboard', 'dashboard', $admin);
?>
  <p class="lead">Welcome back, <strong><?= e($admin['name']) ?></strong>.</p>

  <section class="stats" aria-label="Website content overview">
    <?php foreach ($stats as [$label, $icon, $url, $total, $published]): ?>
      <a class="stat" href="<?= e($url) ?>">
        <span class="stat__icon"><?= admin_icon($icon) ?></span>
        <span class="stat__value"><?= $total ?></span>
        <span class="stat__label"><?= e($label) ?></span>
        <span class="stat__sub"><?= $published ?> published</span>
      </a>
    <?php endforeach; ?>
    <a class="stat stat--accent" href="/admin/messages">
      <span class="stat__icon"><?= admin_icon('mail') ?></span>
      <span class="stat__value"><?= $unread ?></span>
      <span class="stat__label">Unread messages</span>
      <span class="stat__sub">from the contact form</span>
    </a>
  </section>

  <section class="grid-2">
    <div class="card">
      <h2>Your account</h2>
      <dl class="meta">
        <dt>Previous login</dt>
        <dd><?= $prevLogin ? e(date('j M Y, g:i a', strtotime((string) $prevLogin))) . ($prevIp ? ' <span class="muted">from ' . e($prevIp) . '</span>' : '') : 'This is your first login.' ?></dd>
        <dt>Logged in now</dt>
        <dd><?= e(date('j M Y, g:i a')) ?></dd>
        <dt>Auto logout</dt>
        <dd>After 30 minutes without activity</dd>
      </dl>
      <p class="muted small">If the previous login wasn’t you, change your password straight away.</p>
      <a class="btn btn--ghost" href="/admin/password"><?= admin_icon('lock') ?><span>Change password</span></a>
    </div>
    <div class="card card--dark">
      <h2>Your website</h2>
      <p>See the public website exactly as visitors see it.</p>
      <a class="btn" href="/" target="_blank" rel="noopener"><?= admin_icon('external') ?><span>View website</span></a>
    </div>
  </section>
<?php
admin_page_end();
