<?php
/**
 * Shared admin layout: sidebar + top bar + page frame.
 *
 *   admin_page_start('Dashboard', 'dashboard', $admin);
 *   ... page content ...
 *   admin_page_end();
 */
declare(strict_types=1);

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

/** Simple line icons (inline SVG, no external files or fonts). */
function admin_icon(string $name): string
{
    $paths = [
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'sliders'   => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
        'building'  => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h2a2 2 0 0 1 2 2v10M2 21h20M8 7h4M8 11h4M8 15h4"/>',
        'handshake' => '<path d="M8 12l3 3a2 2 0 0 0 3-3l-3-3-2 1-2-2 3-3h3l5 5-3 3M3 9l4-4 2 2M3 9l6 6M16 17l-2-2"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
        'pen'       => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/>',
        'award'     => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
        'quote'     => '<path d="M7 7h4v4c0 3-1.5 5-4 6M15 7h4v4c0 3-1.5 5-4 6"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'image'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'lock'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'logout'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
        'external'  => '<path d="M14 4h6v6M20 4l-9 9M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
}

/** Opening HTML: head, sidebar, top bar. */
function admin_page_start(string $title, string $active, array $admin): void
{
    $menu = admin_menu();
    ?><!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> – Majid Traders Admin</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/admin.css">
<script src="/assets/js/admin.js"></script>
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
        <li><a href="<?= e($url) ?>"<?= $key === $active ? ' class="is-active" aria-current="page"' : '' ?>><?= admin_icon($icon) ?><span><?= e($label) ?></span></a></li>
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
    <a class="btn btn--ghost" href="/" target="_blank" rel="noopener"><?= admin_icon('external') ?><span>View website</span></a>
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
