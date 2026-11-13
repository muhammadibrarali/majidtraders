<?php
/**
 * Public website framework — used by every public page.
 *
 *   define('MT_PUBLIC_PAGE', true);       // no session cookie for normal visitors
 *   require __DIR__ . '/includes/site.php';
 *   site_gate(true);                      // coming-soon / preview / live logic
 *   site_head([...meta...]);  …page…  site_footer();
 *
 * Everything shown comes from the database (settings, services, …).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/media_lib.php';
require_once __DIR__ . '/icons.php';

/** Pinned front-end libraries (jsDelivr) with Subresource Integrity hashes. */
const SITE_SCRIPTS = [
    'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/gsap.min.js'          => 'sha384-XmJ9SoHtVOHoQUcKvFAzVXwdkKo1Ie3bhmSoIAkcdsHGaIrVJIkmozyq0FJeb/Ly',
    'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/ScrollTrigger.min.js' => 'sha384-wl5TeDVvOWt30Pbf8aSo2ZrzsOjddu3avOBvHe+p+OhJt9gP6w9YXmDkN5DK2/dF',
    'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/SplitText.min.js'     => 'sha384-SWJ0lLVRoipvHh59xj0pL7uC7Ih51F+5smaFtrG+2nr+TlDZU5SYJHmxfolbeNTr',
    'https://cdn.jsdelivr.net/npm/lenis@1.3.26/dist/lenis.min.js'        => 'sha384-jqpi9VmOdhyLoLURgjCn7EpnG9BbnHW57ibIZoeaIU+erWDH3k8fQQg0xH2ySjnw',
];
const SITE_ASSET_VERSION = '5'; // bump to make browsers fetch new CSS/JS

// ---------------------------------------------------------------------------
// Live / preview / coming soon
// ---------------------------------------------------------------------------

function site_is_live(): bool
{
    return setting('site_live', '0') === '1';
}

/** True while a logged-in admin looks at the not-yet-live website. */
function site_is_preview(): bool
{
    static $preview = null;
    if ($preview === null) {
        $preview = !site_is_live() && session_status() === PHP_SESSION_ACTIVE && current_admin() !== null;
    }
    return $preview;
}

/**
 * Decide what this visitor sees.
 *  - live:            everyone sees the new website
 *  - not live, admin: new website + "Preview mode" bar (not indexed)
 *  - not live, other: home shows the coming-soon page, other pages go home
 */
function site_gate(bool $isHome = false): void
{
    if (site_is_live()) {
        return;
    }
    if (site_is_preview()) {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store');
        return;
    }
    if ($isHome) {
        require __DIR__ . '/coming_soon.php';
        exit;
    }
    redirect('/');
}

// ---------------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------------

/** Setting with a fallback when empty. */
function s(string $key, string $default = ''): string
{
    return setting($key, $default);
}

/** Media rows by ID (one query), keyed by ID. */
function site_media(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $stmt = db()->prepare('SELECT * FROM media WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $stmt->execute($ids);
    $out = [];
    foreach ($stmt as $m) {
        $out[(int) $m['id']] = $m;
    }
    return $out;
}

/** Width/height of a stored size (images are scaled to fit max 1920/1024/400 px). */
function media_dims(array $m, string $size): array
{
    $w = (int) $m['width'];
    $h = (int) $m['height'];
    if ($w < 1 || $h < 1) {
        return [0, 0];
    }
    $max = MEDIA_SIZES[$size]['max'] ?? 1920;
    $scale = min(1, $max / max($w, $h));
    // lg is already ≤1920, so md/sm are computed from the original size
    return [(int) round($w * $scale), (int) round($h * $scale)];
}

/**
 * Responsive <img>: srcset from the three stored sizes.
 * $opt: sizes, class, alt (overrides media alt), lazy (default true), priority (hero), default (src size)
 */
function media_img(?array $m, array $opt = []): string
{
    if (!$m || $m['kind'] !== 'image') {
        return '';
    }
    $set = [];
    $seen = [];
    foreach (['sm', 'md', 'lg'] as $size) {
        [$w] = media_dims($m, $size);
        if ($w > 0 && !isset($seen[$w])) {
            $seen[$w] = true;
            $set[] = media_url($m, $size) . ' ' . $w . 'w';
        }
    }
    [$w, $h] = media_dims($m, $opt['default'] ?? 'md');
    $lazy = $opt['lazy'] ?? true;
    return '<img src="' . e(media_url($m, $opt['default'] ?? 'md')) . '"'
        . ($set ? ' srcset="' . e(implode(', ', $set)) . '" sizes="' . e($opt['sizes'] ?? '100vw') . '"' : '')
        . ($w ? ' width="' . $w . '" height="' . $h . '"' : '')
        . ' alt="' . e($opt['alt'] ?? (string) $m['alt_text']) . '"'
        . (isset($opt['class']) ? ' class="' . e($opt['class']) . '"' : '')
        . ($lazy ? ' loading="lazy" decoding="async"' : ' decoding="async"')
        . (!empty($opt['priority']) ? ' fetchpriority="high"' : '')
        . '>';
}

/** WhatsApp chat link (number from settings), or '' if not set. */
function whatsapp_link(string $text = ''): string
{
    $n = preg_replace('/\D+/', '', s('whatsapp'));
    if ($n === '' || strlen($n) < 10) {
        return '';
    }
    $text = $text !== '' ? $text : 'Hello Majid Traders, I would like to ask about ';
    return 'https://wa.me/' . $n . '?text=' . rawurlencode($text);
}

/** tel: link for a Pakistani number like "0300 5741542" -> tel:+923005741542 */
function tel_link(string $phone): string
{
    $d = preg_replace('/[^\d+]/', '', $phone) ?? '';
    if (str_starts_with($d, '00')) {
        $d = '+' . substr($d, 2);
    } elseif (str_starts_with($d, '0')) {
        $d = '+92' . substr($d, 1);
    }
    return 'tel:' . $d;
}

/** Social links that are filled in: [icon, label, url] */
function social_links(): array
{
    $out = [];
    foreach (['facebook_url' => ['facebook', 'Facebook'], 'instagram_url' => ['instagram', 'Instagram'], 'linkedin_url' => ['linkedin', 'LinkedIn'],
              'youtube_url' => ['youtube', 'YouTube'], 'tiktok_url' => ['tiktok', 'TikTok'], 'x_url' => ['x-social', 'X']] as $key => [$icon, $label]) {
        $url = s($key);
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            $out[] = [$icon, $label, $url];
        }
    }
    return $out;
}

/** Published services for menus/footers. */
function site_services(): array
{
    static $list = null;
    return $list ??= db()->query("SELECT id, title, slug, icon, excerpt, image_id FROM services WHERE status = 'published' ORDER BY sort_order, id")->fetchAll();
}

function site_nav(): array
{
    return [
        'home' => ['Home', '/'], 'about' => ['About', '/about'], 'services' => ['Services', '/services'],
        'projects' => ['Projects', '/projects'], 'clients' => ['Clients', '/clients'], 'blog' => ['Blog', '/blog'],
        'contact' => ['Contact', '/contact'],
    ];
}

function site_url(string $path = '/'): string
{
    return rtrim((string) config('site.url', 'https://majidtraders.org'), '/') . $path;
}

/** JSON for <script type="application/ld+json"> (safe inside HTML). */
function json_ld(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
}

/** Logo markup: uploaded logo, or the MT badge + company name. */
function site_logo_html(string $variant = 'header'): string
{
    $logo = setting_media_id('logo_id');
    $m = $logo ? (site_media([$logo])[$logo] ?? null) : null;
    $name = s('company_name', 'Majid Traders');
    if ($m && $m['kind'] === 'image') {
        [$w, $h] = media_dims($m, 'sm');
        return '<img class="logo__img" src="' . e(media_url($m, 'sm')) . '" width="' . $w . '" height="' . $h . '" alt="' . e($name) . '">';
    }
    return '<span class="logo__badge" aria-hidden="true">MT</span><span class="logo__text">' . e($name)
        . ($variant === 'footer' ? '' : '<small>' . e(s('tagline')) . '</small>') . '</span>';
}

// ---------------------------------------------------------------------------
// Page frame
// ---------------------------------------------------------------------------

/**
 * <head> + header. $meta keys:
 *   title, description, path ('/'), og_image (media row), active (nav key),
 *   hero (bool: transparent header over a hero), preload (media row to preload), jsonld (array)
 */
function site_head(array $meta): void
{
    $title = $meta['title'];
    $desc = $meta['description'] ?? s('default_meta_description');
    $canonical = site_url($meta['path'] ?? '/');
    $og = $meta['og_image'] ?? null;
    if (!$og && ($id = setting_media_id('default_og_image_id'))) {
        $og = site_media([$id])[$id] ?? null;
    }
    if (!$og && !empty($meta['preload'])) {
        $og = $meta['preload']; // no share image chosen: use the page's main image
    }
    $fav = setting_media_id('favicon_id');
    $favM = $fav ? (site_media([$fav])[$fav] ?? null) : null;
    $preview = site_is_preview();
    ?><!doctype html>
<html lang="en-PK" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($preview): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<meta name="theme-color" content="#1E2124">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(s('company_name', 'Majid Traders')) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:locale" content="en_PK">
<?php if ($og && $og['kind'] === 'image'): [$ow, $oh] = media_dims($og, 'lg'); ?>
<meta property="og:image" content="<?= e(site_url(media_url($og, 'lg'))) ?>">
<meta property="og:image:width" content="<?= $ow ?>"><meta property="og:image:height" content="<?= $oh ?>">
<meta property="og:image:alt" content="<?= e((string) $og['alt_text']) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<?php if ($favM && $favM['kind'] === 'image'): ?>
<link rel="icon" href="<?= e(media_url($favM, 'sm')) ?>" type="<?= e($favM['mime_type']) ?>">
<link rel="apple-touch-icon" href="<?= e(media_url($favM, 'sm')) ?>">
<?php else: ?>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<?php endif; ?>
<link rel="preload" href="/assets/fonts/bricolage-grotesque-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<?php if (!empty($meta['preload']) && $meta['preload']['kind'] === 'image'): $p = $meta['preload'];
    $set = [];
    foreach (['sm', 'md', 'lg'] as $sz) { $set[] = media_url($p, $sz) . ' ' . media_dims($p, $sz)[0] . 'w'; } ?>
<link rel="preload" as="image" href="<?= e(media_url($p, 'lg')) ?>" imagesrcset="<?= e(implode(', ', $set)) ?>" imagesizes="100vw" fetchpriority="high">
<?php endif; ?>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="/assets/css/site.css?v=<?= SITE_ASSET_VERSION ?>">
<?php foreach (SITE_SCRIPTS as $src => $sri): ?>
<script src="<?= e($src) ?>" integrity="<?= e($sri) ?>" crossorigin="anonymous" defer></script>
<?php endforeach; ?>
<script src="/assets/js/site.js?v=<?= SITE_ASSET_VERSION ?>" defer></script>
<?php if (!empty($meta['jsonld'])) { echo json_ld($meta['jsonld']); } ?>
</head>
<body id="top" class="<?= !empty($meta['hero']) ? 'has-hero' : '' ?><?= $preview ? ' is-preview' : '' ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?php if ($preview): ?>
<div class="preview-bar" role="status">
  <span><strong>Preview mode</strong><span class="preview-bar__long"> — only you can see this. Visitors still see the “coming soon” page.</span></span>
  <a href="/admin/settings?tab=status">Go live</a>
  <a href="/admin/">Admin</a>
</div>
<?php endif; ?>
<?php site_header($meta['active'] ?? ''); ?>
<main id="main">
<?php
}

function site_header(string $active): void
{
    $portal = s('portal_url', 'https://portal.majidtraders.org');
    ?>
<header class="site-header" data-header>
  <div class="container site-header__inner">
    <a class="logo" href="/" aria-label="<?= e(s('company_name', 'Majid Traders')) ?> — home"><?= site_logo_html() ?></a>
    <nav class="site-nav" aria-label="Main">
      <ul>
        <?php foreach (site_nav() as $key => [$label, $url]): ?>
          <li><a href="<?= e($url) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="site-header__actions">
      <a class="staff-link" href="<?= e($portal) ?>" rel="noopener">Staff Login</a>
      <a class="btn btn--primary btn--sm site-header__cta" href="/contact">Get a Quote</a>
      <a class="menu-toggle" href="#site-footer" data-menu-toggle aria-controls="mobile-menu" aria-expanded="false">
        <span class="menu-toggle__bars" aria-hidden="true"><span></span><span></span></span><span class="visually-hidden">Menu</span>
      </a>
    </div>
  </div>
</header>

<div class="mobile-menu" id="mobile-menu" data-mobile-menu hidden>
  <div class="mobile-menu__inner container">
    <nav aria-label="Mobile">
      <ul class="mobile-menu__links">
        <?php foreach (site_nav() as $key => [$label, $url]): ?>
          <li><a href="<?= e($url) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="mobile-menu__foot">
      <a class="btn btn--primary" href="/contact">Get a Quote <?= icon_svg('arrow-right', 18) ?></a>
      <?php if (s('phone')): ?><a href="<?= e(tel_link(s('phone'))) ?>"><?= icon_svg('phone', 18) ?> <?= e(s('phone')) ?></a><?php endif; ?>
      <?php if (s('email')): ?><a href="mailto:<?= e(s('email')) ?>"><?= icon_svg('mail', 18) ?> <?= e(s('email')) ?></a><?php endif; ?>
      <a href="<?= e($portal) ?>" rel="noopener"><?= icon_svg('lock', 18) ?> Staff Login</a>
    </div>
  </div>
</div>
<?php
}

function site_footer(): void
{
    $portal = s('portal_url', 'https://portal.majidtraders.org');
    $wa = whatsapp_link();
    $services = site_services();
    $social = social_links();
    ?>
</main>

<footer class="site-footer" id="site-footer">
  <div class="container site-footer__grid">
    <div class="site-footer__brand">
      <a class="logo logo--footer" href="/"><?= site_logo_html('footer') ?></a>
      <?php if (s('tagline')): ?><p class="site-footer__tagline"><?= e(s('tagline')) ?></p><?php endif; ?>
      <?php if (s('company_intro')): ?><p><?= e(s('company_intro')) ?></p><?php endif; ?>
      <?php if ($social): ?>
        <ul class="social" aria-label="Social media">
          <?php foreach ($social as [$icon, $label, $url]): ?>
            <li><a href="<?= e($url) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>"><?= icon_svg($icon, 20) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <nav class="site-footer__col" aria-label="Quick links">
      <h2 class="site-footer__title">Quick links</h2>
      <ul>
        <?php foreach (site_nav() as [$label, $url]): ?><li><a href="<?= e($url) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
        <li><a href="/certifications">Certifications</a></li>
        <li><a href="/team">Our team</a></li>
      </ul>
    </nav>
    <?php if ($services): ?>
    <nav class="site-footer__col" aria-label="Services">
      <h2 class="site-footer__title">Services</h2>
      <ul>
        <?php foreach ($services as $svc): ?><li><a href="/services/<?= e(rawurlencode($svc['slug'])) ?>"><?= e($svc['title']) ?></a></li><?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
    <div class="site-footer__col">
      <h2 class="site-footer__title">Contact</h2>
      <address>
        <ul class="contact-list">
          <?php if (s('address')): ?><li><?= icon_svg('map-pin', 18) ?><span><?= e(s('address')) ?></span></li><?php endif; ?>
          <?php if (s('phone')): ?><li><?= icon_svg('phone', 18) ?><a href="<?= e(tel_link(s('phone'))) ?>"><?= e(s('phone')) ?></a></li><?php endif; ?>
          <?php if ($wa): ?><li><?= icon_svg('whatsapp', 18) ?><a href="<?= e($wa) ?>" rel="noopener" target="_blank">WhatsApp us</a></li><?php endif; ?>
          <?php if (s('email')): ?><li><?= icon_svg('mail', 18) ?><a href="mailto:<?= e(s('email')) ?>"><?= e(s('email')) ?></a></li><?php endif; ?>
          <?php if (s('office_hours')): ?><li><?= icon_svg('clock', 18) ?><span><?= e(s('office_hours')) ?></span></li><?php endif; ?>
        </ul>
      </address>
    </div>
  </div>
  <div class="container site-footer__bottom">
    <p>© <?= date('Y') ?> <?= e(s('company_name', 'Majid Traders')) ?>. All rights reserved.</p>
    <ul>
      <li><a href="/privacy-policy">Privacy Policy</a></li>
      <li><a href="<?= e($portal) ?>" rel="noopener">Staff Login</a></li>
    </ul>
  </div>
</footer>

<?php if ($wa): ?>
<a class="fab-whatsapp" href="<?= e($wa) ?>" rel="noopener" target="_blank" aria-label="Chat with us on WhatsApp"><?= icon_svg('whatsapp', 26) ?></a>
<?php endif; ?>
<a class="to-top" href="#top" data-to-top aria-label="Back to top">
  <svg class="to-top__ring" viewBox="0 0 48 48" aria-hidden="true"><circle class="to-top__track" cx="24" cy="24" r="21"/><circle class="to-top__bar" cx="24" cy="24" r="21" pathLength="100" data-progress/></svg>
  <?= icon_svg('arrow-up', 20) ?>
</a>
</body>
</html>
<?php
}

/** Section heading: small orange label + heading. */
function section_head(string $eyebrow, string $title, string $id, string $extraClass = ''): string
{
    if ($title === '' && $eyebrow === '') {
        return '';
    }
    return '<div class="section-head ' . e($extraClass) . '">'
        . ($eyebrow !== '' ? '<p class="eyebrow" data-reveal>' . e($eyebrow) . '</p>' : '')
        . ($title !== '' ? '<h2 class="section-title" id="' . e($id) . '" data-split>' . e($title) . '</h2>' : '')
        . '</div>';
}
