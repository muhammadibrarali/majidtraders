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

// [label, icon, link, total, sub-text]
$stats = [];
foreach ([
    ['Services',       'briefcase', '/admin/services',       'services',       'published'],
    ['Projects',       'building',  '/admin/projects',       'projects',       'published'],
    ['Clients',        'handshake', '/admin/clients',        'clients',        'published'],
    ['Team',           'users',     '/admin/team',           'team_members',   'shown on website'],
    ['Certifications', 'award',     '/admin/certifications', 'certifications', 'published'],
    ['Testimonials',   'quote',     '/admin/testimonials',   'testimonials',   'published'],
] as [$label, $icon, $url, $table, $word]) {
    [$total, $published] = $count($table);
    $stats[] = [$label, $icon, $url, $total, $published . ' ' . $word];
}
$blog = $pdo->query("SELECT COUNT(*) AS total,
        COALESCE(SUM(status = 'published' AND (published_at IS NULL OR published_at <= NOW())), 0) AS live,
        COALESCE(SUM(status = 'published' AND published_at > NOW()), 0) AS scheduled FROM blog_posts")->fetch();
array_splice($stats, 4, 0, [['Blog posts', 'pen', '/admin/blog', (int) $blog['total'],
    (int) $blog['live'] . ' live' . ((int) $blog['scheduled'] ? ' · ' . (int) $blog['scheduled'] . ' scheduled' : '')]]);
$mediaCount = (int) $pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();
$unread = admin_unread_count();

// Certificates expiring within 60 days (or already expired)
$expiring = $pdo->query("SELECT id, title, registration_number, expiry_date, DATEDIFF(expiry_date, CURDATE()) AS days_left
                         FROM certifications WHERE expiry_date IS NOT NULL AND expiry_date <= CURDATE() + INTERVAL 60 DAY
                         ORDER BY expiry_date")->fetchAll();

$prevLogin = $_SESSION['admin_prev_login'] ?? null;
$prevIp    = $_SESSION['admin_prev_ip'] ?? null;

admin_page_start('Dashboard', 'dashboard', $admin);
?>
  <p class="lead">Welcome back, <strong><?= e($admin['name']) ?></strong>.</p>

  <?php if ($expiring): ?>
  <section class="card card--warning" aria-labelledby="expiry-title">
    <h2 id="expiry-title"><?= admin_icon('award') ?> <span>Certificates need attention</span></h2>
    <ul class="expiry-list">
      <?php foreach ($expiring as $c): $d = (int) $c['days_left']; ?>
        <li>
          <a href="/admin/certifications?action=edit&amp;id=<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a>
          <?= $c['registration_number'] ? '<span class="muted">(' . e($c['registration_number']) . ')</span>' : '' ?>
          <span class="badge badge--<?= $d < 0 ? 'danger' : 'warn' ?>"><?= $d < 0 ? 'Expired ' . abs($d) . ' day' . (abs($d) === 1 ? '' : 's') . ' ago' : ($d === 0 ? 'Expires today' : 'Expires in ' . $d . ' day' . ($d === 1 ? '' : 's')) ?></span>
          <span class="muted small"><?= e(date('j M Y', strtotime((string) $c['expiry_date']))) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="muted small">Renew these, then update the expiry date and document under Certifications.</p>
  </section>
  <?php endif; ?>

  <section class="stats" aria-label="Website content overview">
    <a class="stat stat--accent" href="/admin/messages">
      <span class="stat__icon"><?= admin_icon('mail') ?></span>
      <span class="stat__value"><?= $unread ?></span>
      <span class="stat__label">Unread messages</span>
      <span class="stat__sub">from the contact form</span>
    </a>
    <?php foreach ($stats as [$label, $icon, $url, $total, $sub]): ?>
      <a class="stat" href="<?= e($url) ?>">
        <span class="stat__icon"><?= admin_icon($icon) ?></span>
        <span class="stat__value"><?= $total ?></span>
        <span class="stat__label"><?= e($label) ?></span>
        <span class="stat__sub"><?= e($sub) ?></span>
      </a>
    <?php endforeach; ?>
    <a class="stat" href="/admin/media">
      <span class="stat__icon"><?= admin_icon('image') ?></span>
      <span class="stat__value"><?= $mediaCount ?></span>
      <span class="stat__label">Media files</span>
      <span class="stat__sub">images &amp; PDFs</span>
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
      <a class="btn btn--text" href="/admin/health"><?= admin_icon('check') ?><span>Website health check</span></a>
    </div>
    <div class="card card--dark">
      <h2>Your website</h2>
      <p>See the public website exactly as visitors see it.</p>
      <a class="btn" href="/" target="_blank" rel="noopener"><?= admin_icon('external') ?><span>View website</span></a>
    </div>
  </section>
<?php
admin_page_end();
