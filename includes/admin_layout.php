<?php
/**
 * Shared admin layout: sidebar + top bar + page frame.
 *
 *   admin_page_start('Dashboard', 'dashboard', $admin);
 *   ... page content ...
 *   admin_page_end();
 */
declare(strict_types=1);

require_once __DIR__ . '/icons.php';

/** Sidebar menu: key => [label, url, icon]. */
function admin_menu(): array
{
    return [
        'dashboard'      => ['Dashboard',       '/admin/',               'grid'],
        'settings'       => ['Site Settings',   '/admin/settings',       'sliders'],
        'services'       => ['Services',        '/admin/services',       'briefcase'],
        'projects'       => ['Projects',        '/admin/projects',       'building'],
        'clients'        => ['Clients',         '/admin/clients',        'handshake'],
        'team'           => ['Team',            '/admin/team',           'users'],
        'blog'           => ['Blog',            '/admin/blog',           'pen'],
        'certifications' => ['Certifications',  '/admin/certifications', 'award'],
        'testimonials'   => ['Testimonials',    '/admin/testimonials',   'quote'],
        'messages'       => ['Messages',        '/admin/messages',       'mail'],
        'media'          => ['Media Library',   '/admin/media',          'image'],
        'password'       => ['Change Password', '/admin/password',       'lock'],
    ];
}

/** Admin icon (see includes/icons.php for the full set). */
function admin_icon(string $name): string
{
    return icon_svg($name);
}

/** Number of unread contact-form messages (for the sidebar badge). */
function admin_unread_count(): int
{
    static $n = null;
    if ($n === null) {
        try {
            $n = (int) db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
        } catch (Throwable) {
            $n = 0;
        }
    }
    return $n;
}

/** Opening HTML: head, sidebar, top bar. */
function admin_page_start(string $title, string $active, array $admin, string $actions = ''): void
{
    $menu = admin_menu();
    $unread = admin_unread_count();
    ?><!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> – Majid Traders Admin</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
<script src="<?= e(asset('/assets/js/admin.js')) ?>"></script>
</head>
<body class="admin">
<a class="skip-link" href="#main">Skip to content</a>

<header class="topbar">
  <button type="button" class="topbar__menu" data-menu-toggle aria-controls="sidebar" aria-expanded="false">
    <?= admin_icon('menu') ?><span class="visually-hidden">Open menu</span>
  </button>
  <a class="brand" href="/admin/"><span class="brand__logo" aria-hidden="true">MT</span><span class="brand__name">Majid Traders</span></a>
</header>

<aside id="sidebar" class="sidebar" aria-label="Admin menu">
  <div class="sidebar__head">
    <a class="brand" href="/admin/"><span class="brand__logo" aria-hidden="true">MT</span><span class="brand__name">Majid Traders<small>Admin panel</small></span></a>
    <button type="button" class="sidebar__close" data-menu-toggle aria-controls="sidebar">
      <?= admin_icon('close') ?><span class="visually-hidden">Close menu</span>
    </button>
  </div>
  <nav>
    <ul class="nav">
      <?php foreach ($menu as $key => [$label, $url, $icon]): ?>
        <li><a href="<?= e($url) ?>"<?= $key === $active ? ' class="is-active" aria-current="page"' : '' ?>><?= admin_icon($icon) ?><span><?= e($label) ?></span><?php if ($key === 'messages' && $unread > 0): ?><span class="nav__count" title="<?= $unread ?> unread"><?= $unread > 99 ? '99+' : $unread ?><span class="visually-hidden"> unread</span></span><?php endif; ?></a></li>
      <?php endforeach; ?>
      <li>
        <form method="post" action="/admin/logout">
          <?= csrf_field() ?>
          <button type="submit"><?= admin_icon('logout') ?><span>Logout</span></button>
        </form>
      </li>
    </ul>
  </nav>
  <div class="sidebar__user">
    <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($admin['name'], 0, 1))) ?></span>
    <span><strong><?= e($admin['name']) ?></strong><small><?= e($admin['email']) ?></small></span>
  </div>
</aside>
<div class="sidebar-backdrop" data-menu-toggle hidden></div>

<main id="main" class="main">
  <div class="page-head">
    <h1><?= e($title) ?></h1>
    <div class="page-head__actions">
      <?= $actions /* trusted HTML built by the page itself */ ?>
      <a class="btn btn--ghost" href="/" target="_blank" rel="noopener"><?= admin_icon('external') ?><span>View website</span></a>
    </div>
  </div>
  <?php foreach (take_flashes() as $f): ?>
    <div class="notice notice--<?= $f['type'] === 'success' ? 'ok' : ($f['type'] === 'error' ? 'error' : 'info') ?>" role="status"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
<?php
}

/** Closing HTML. */
function admin_page_end(): void
{
    ?>
</main>
</body>
</html>
<?php
}

/** Placeholder for sections that are not built yet. */
function admin_coming_soon(string $active): never
{
    $admin = require_admin();
    $label = admin_menu()[$active][0] ?? 'Section';
    admin_page_start($label, $active, $admin);
    ?>
  <section class="card empty">
    <div class="empty__icon"><?= admin_icon(admin_menu()[$active][2] ?? 'grid') ?></div>
    <h2>Coming in the next step</h2>
    <p class="muted">The <?= e($label) ?> manager hasn’t been built yet. It will appear here in a coming update.</p>
    <a class="btn" href="/admin/">Back to dashboard</a>
  </section>
<?php
    admin_page_end();
    exit;
}
