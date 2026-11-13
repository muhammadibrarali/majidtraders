<?php
/**
 * Shared building blocks for public pages (Step 6+).
 * Loaded by includes/site.php.
 */
declare(strict_types=1);

/** Is the visitor a logged-in admin? (Only possible when they already have the admin cookie.) */
function site_is_admin(): bool
{
    static $admin = null;
    if ($admin === null) {
        $admin = session_status() === PHP_SESSION_ACTIVE && current_admin() !== null;
    }
    return $admin;
}

/**
 * Load one item by slug for a detail page.
 * Public visitors only get items where $isPublic($row) is true; a logged-in
 * admin also gets drafts/scheduled items (shown with the "Draft – not public" bar).
 * Returns [row, isDraft]. Shows the 404 page when nothing may be shown.
 */
function site_load(string $table, string $slug, ?callable $isPublic = null): array
{
    $stmt = db()->prepare("SELECT * FROM `$table` WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if (!$row) {
        show_error_page(404);
    }
    $public = $isPublic ? $isPublic($row) : ($row['status'] === 'published');
    if (!$public && !site_is_admin()) {
        show_error_page(404);
    }
    return [$row, !$public];
}

/** Is a blog post visible to the public now? (published and its date has come) */
function post_is_live(array $p): bool
{
    return $p['status'] === 'published' && (!$p['published_at'] || strtotime((string) $p['published_at']) <= time());
}

// ---------------------------------------------------------------------------
// Breadcrumbs + JSON-LD
// ---------------------------------------------------------------------------

/** $crumbs = [['Home', '/'], ['Services', '/services'], ['Import & Export', null]] */
function breadcrumb_ld(array $crumbs): array
{
    $items = [];
    foreach (array_values($crumbs) as $i => [$label, $path]) {
        $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $label];
        $item['item'] = site_url($path ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        $items[] = $item;
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/** Full JSON-LD for a page: breadcrumbs + extra nodes. */
function page_jsonld(array $crumbs, array $extra = []): array
{
    return ['@context' => 'https://schema.org', '@graph' => array_values(array_merge([breadcrumb_ld($crumbs)], $extra))];
}

function site_breadcrumbs_html(array $crumbs): string
{
    $last = count($crumbs) - 1;
    $html = '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>';
    foreach (array_values($crumbs) as $i => [$label, $path]) {
        $html .= $i === $last || $path === null
            ? '<li><span aria-current="page">' . e($label) . '</span></li>'
            : '<li><a href="' . e($path) . '">' . e($label) . '</a></li>';
    }
    return $html . '</ol></nav>';
}

// ---------------------------------------------------------------------------
// Page header
// ---------------------------------------------------------------------------

/**
 * Animated page header (dark, with a soft background image).
 * $o: title, crumbs, lead, eyebrow, image (media row), extra (trusted HTML: badges/buttons)
 */
function site_page_header(array $o): void
{
    $img = $o['image'] ?? null;
    ?>
<section class="page-hero<?= $img ? '' : ' page-hero--plain' ?>" aria-labelledby="page-title">
  <div class="page-hero__media" data-parallax>
    <?php if ($img): ?><?= media_img($img, ['sizes' => '100vw', 'default' => 'lg', 'lazy' => false, 'priority' => true, 'class' => 'page-hero__img', 'alt' => '']) ?><?php endif; ?>
  </div>
  <div class="page-hero__shade" aria-hidden="true"></div>
  <div class="container page-hero__content">
    <?= site_breadcrumbs_html($o['crumbs']) ?>
    <?php if (!empty($o['eyebrow'])): ?><p class="eyebrow eyebrow--light page-hero__eyebrow"><?= e($o['eyebrow']) ?></p><?php endif; ?>
    <h1 class="page-hero__title" id="page-title"><?php foreach (preg_split('/\s+/', trim((string) $o['title'])) as $w): ?><span class="hero__word"><span><?= e($w) ?></span></span> <?php endforeach; ?></h1>
    <?php if (!empty($o['lead'])): ?><p class="page-hero__lead"><?= e($o['lead']) ?></p><?php endif; ?>
    <?php if (!empty($o['extra'])): ?><div class="page-hero__extra"><?= $o['extra'] /* trusted HTML built by the page */ ?></div><?php endif; ?>
  </div>
</section>
<?php
}

// ---------------------------------------------------------------------------
// Reusable sections
// ---------------------------------------------------------------------------

/** Closing "Let's talk" band (texts from Site Settings › Home page sections). */
function site_cta(): void
{
    if (s('home_cta_heading') === '') {
        return;
    }
    $wa = whatsapp_link();
    ?>
<section class="cta-band" aria-labelledby="cta-title">
  <div class="container cta-band__inner">
    <div class="cta-band__glow" aria-hidden="true"></div>
    <h2 class="cta-band__title" id="cta-title" data-split><?= e(s('home_cta_heading')) ?></h2>
    <?php if (s('home_cta_text')): ?><p class="cta-band__text" data-reveal><?= e(s('home_cta_text')) ?></p><?php endif; ?>
    <div class="cta-band__actions" data-reveal>
      <a class="btn btn--primary btn--lg" href="/contact">Contact us <?= icon_svg('arrow-right', 20) ?></a>
      <?php if ($wa): ?><a class="btn btn--ghost-light btn--lg" href="<?= e($wa) ?>" rel="noopener" target="_blank"><?= icon_svg('whatsapp', 20) ?> WhatsApp</a><?php endif; ?>
    </div>
  </div>
</section>
<?php
}

/** Friendly "nothing here yet" box. */
function site_empty(string $icon, string $title, string $text, string $btnText = '', string $btnUrl = ''): string
{
    return '<div class="empty-state" data-reveal><span class="empty-state__icon">' . icon_svg($icon, 36) . '</span>'
        . '<h2 class="empty-state__title">' . e($title) . '</h2><p>' . e($text) . '</p>'
        . ($btnText !== '' ? '<a class="btn btn--secondary" href="' . e($btnUrl) . '">' . e($btnText) . ' ' . icon_svg('arrow-right', 18) . '</a>' : '')
        . '</div>';
}

/** Published counters band. */
function render_counters(): string
{
    $counters = db()->query("SELECT label, value, prefix, suffix FROM counters WHERE status = 'published' ORDER BY sort_order, id")->fetchAll();
    if (!$counters) {
        return '';
    }
    $html = '<section class="counters" aria-label="Majid Traders in numbers"><div class="container"><ul class="counters__grid counters__grid--' . min(4, count($counters)) . '" data-reveal-group>';
    foreach ($counters as $c) {
        $html .= '<li class="counter" data-reveal-item><span class="counter__value">' . e((string) $c['prefix'])
            . '<span data-count="' . (int) $c['value'] . '">' . number_format((int) $c['value']) . '</span>' . e((string) $c['suffix'])
            . '</span><span class="counter__label">' . e($c['label']) . '</span></li>';
    }
    return $html . '</ul></div></section>';
}

/** Published certificates as a list of badges. */
function render_cert_list(?array $certs = null): string
{
    $certs ??= db()->query("SELECT id, title, slug, issuing_authority, registration_number, grade, show_number, document_id
                            FROM certifications WHERE status = 'published' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY sort_order, id")->fetchAll();
    if (!$certs) {
        return '';
    }
    $media = site_media(array_column($certs, 'document_id'));
    $html = '<ul class="certs__list" data-reveal-group>';
    foreach ($certs as $c) {
        $doc = $media[(int) $c['document_id']] ?? null;
        $html .= '<li class="cert" id="' . e($c['slug']) . '" data-reveal-item><span class="cert__badge">'
            . ($doc && $doc['kind'] === 'image' ? media_img($doc, ['default' => 'sm', 'sizes' => '64px', 'alt' => '']) : icon_svg('award', 30))
            . '</span><span class="cert__body"><span class="cert__name">' . e($c['title']) . '</span>'
            . ($c['grade'] ? '<span class="badge badge--accent">Category ' . e($c['grade']) . '</span>' : '')
            . '<span class="cert__meta">' . e(implode(' · ', array_filter([(string) $c['issuing_authority'], $c['show_number'] && $c['registration_number'] ? 'No. ' . $c['registration_number'] : '']))) . '</span>'
            . '</span></li>';
    }
    return $html . '</ul>';
}

/** Service card for grids (thumbnail, icon, title, summary). */
function service_card_html(array $svc, ?array $img): string
{
    return '<li class="grid-card" data-reveal-item><a class="service-card" href="/services/' . e(rawurlencode($svc['slug'])) . '">'
        . ($img ? '<span class="service-card__thumb">' . media_img($img, ['sizes' => '(min-width: 1024px) 30vw, (min-width: 640px) 50vw, 100vw', 'alt' => '']) . '</span>' : '')
        . '<span class="service-card__body">'
        . ($svc['icon'] ? '<span class="service-card__icon">' . icon_svg($svc['icon'], 28) . '</span>' : '')
        . '<h3 class="service-card__title">' . e($svc['title']) . '</h3>'
        . ($svc['excerpt'] ? '<p class="service-card__text">' . e($svc['excerpt']) . '</p>' : '')
        . '<span class="service-card__more">Learn more ' . icon_svg('arrow-right', 18) . '</span>'
        . '</span></a></li>';
}

/** Project card (cover, location · year, title, client). $p needs client_name / client_department. */
function project_card_html(array $p, ?array $cover, string $tag = 'li'): string
{
    $client = ($p['client_name'] ?? '') ?: ($p['client_department'] ?? '');
    return '<' . $tag . ' class="project-card" data-reveal-item><a href="/projects/' . e(rawurlencode($p['slug'])) . '">'
        . '<span class="project-card__media">'
        . ($cover ? media_img($cover, ['sizes' => '(min-width: 1024px) 30vw, (min-width: 640px) 50vw, 100vw', 'alt' => '']) : '<span class="project-card__placeholder">' . icon_svg('building', 40) . '</span>')
        . (($p['project_status'] ?? '') === 'ongoing' ? '<span class="badge badge--accent project-card__badge">Ongoing</span>' : '')
        . '</span><span class="project-card__body">'
        . '<span class="project-card__meta">' . e(implode(' · ', array_filter([(string) ($p['location'] ?? ''), (string) ($p['year'] ?? '')]))) . '</span>'
        . '<h3 class="project-card__title">' . e($p['title']) . '</h3>'
        . ($client ? '<span class="project-card__client">' . e($client) . '</span>' : '')
        . '</span></a></' . $tag . '>';
}

/** SELECT for project cards (published client name only). */
const PROJECT_CARD_SQL = "SELECT p.id, p.title, p.slug, p.location, p.year, p.project_status, p.client_department, p.cover_image_id, p.excerpt, p.sort_order,
                                 CASE WHEN c.status = 'published' THEN c.name END AS client_name
                          FROM projects p LEFT JOIN clients c ON c.id = p.client_id";

/**
 * Photo gallery: thumbnails that open the lightbox (or the full image without JS).
 * $items = [['media' => row, 'caption' => '…'], …]
 */
function gallery_html(array $items, string $label): string
{
    $items = array_values(array_filter($items, static fn ($it) => $it['media'] && $it['media']['kind'] === 'image'));
    if (!$items) {
        return '';
    }
    $html = '<ul class="gallery" data-gallery data-reveal-group aria-label="' . e($label) . '">';
    foreach ($items as $i => $it) {
        $m = $it['media'];
        $cap = trim((string) ($it['caption'] ?? '')) ?: (string) $m['alt_text'];
        [$w, $h] = media_dims($m, 'lg');
        $html .= '<li class="gallery__item" data-reveal-item><a class="gallery__link" href="' . e(media_url($m, 'lg')) . '" data-lightbox'
            . ' data-width="' . $w . '" data-height="' . $h . '" data-caption="' . e($cap) . '">'
            . media_img($m, ['sizes' => '(min-width: 1024px) 30vw, (min-width: 640px) 50vw, 100vw', 'alt' => (string) $m['alt_text']])
            . '<span class="visually-hidden">Open photo ' . ($i + 1) . ' of ' . count($items) . ' in large view</span></a>'
            . (trim((string) ($it['caption'] ?? '')) !== '' ? '<p class="gallery__caption">' . e($it['caption']) . '</p>' : '')
            . '</li>';
    }
    return $html . '</ul>';
}

/** Pagination with real links (crawlable). $url(int $page): string */
function site_pagination(int $page, int $pages, callable $url): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pager" aria-label="Pages"><ul>';
    $html .= '<li>' . ($page > 1 ? '<a class="pager__step" href="' . e($url($page - 1)) . '" rel="prev">' . icon_svg('chevron-left', 18) . '<span>Previous</span></a>' : '<span class="pager__step is-disabled">' . icon_svg('chevron-left', 18) . '<span>Previous</span></span>') . '</li>';
    for ($i = 1; $i <= $pages; $i++) {
        if ($pages > 7 && $i !== 1 && $i !== $pages && abs($i - $page) > 1) {
            if ($i === 2 || $i === $pages - 1) {
                $html .= '<li><span class="pager__gap">…</span></li>';
            }
            continue;
        }
        $html .= '<li>' . ($i === $page ? '<span class="pager__num is-current" aria-current="page">' . $i . '</span>' : '<a class="pager__num" href="' . e($url($i)) . '">' . $i . '</a>') . '</li>';
    }
    $html .= '<li>' . ($page < $pages ? '<a class="pager__step" href="' . e($url($page + 1)) . '" rel="next"><span>Next</span>' . icon_svg('chevron-right', 18) . '</a>' : '<span class="pager__step is-disabled"><span>Next</span>' . icon_svg('chevron-right', 18) . '</span>') . '</li>';
    return $html . '</ul></nav>';
}

/** Client type labels (in the order shown on the Clients page). */
function client_type_labels(): array
{
    return [
        'federal_government'    => 'Federal Government',
        'provincial_government' => 'Provincial Government',
        'local_government'      => 'Local Government',
        'semi_government'       => 'Semi-Government',
        'armed_forces'          => 'Armed Forces',
        'private'               => 'Private Sector',
        'international'         => 'International',
        'ngo'                   => 'NGOs',
        'other'                 => 'Other Clients',
    ];
}

/** Rich text from the admin (already cleaned by HTML Purifier when saved). */
function site_prose(?string $html, string $extra = ''): string
{
    $html = trim((string) $html);
    return $html === '' ? '' : '<div class="prose ' . e($extra) . '" data-reveal>' . $html . '</div>';
}

/** Plain text with blank lines -> paragraphs. */
function site_paragraphs(string $text): string
{
    $out = '';
    foreach (preg_split('/\R\s*\R/', trim($text)) ?: [] as $p) {
        if (trim($p) !== '') {
            $out .= '<p>' . nl2br(e(trim($p))) . '</p>';
        }
    }
    return $out;
}

/** Media row for a setting key (header images etc.). */
function setting_media(string $key): ?array
{
    $id = setting_media_id($key);
    return $id ? (site_media([$id])[$id] ?? null) : null;
}


// ---------------------------------------------------------------------------
// Team
// ---------------------------------------------------------------------------

/** Department order on the Team page (same list as the admin). */
function team_departments(): array
{
    return ['Management', 'Engineering', 'Procurement', 'Accounts', 'Field Staff', 'Administration', 'Sales & Marketing', 'Logistics'];
}

// ---------------------------------------------------------------------------
// Blog
// ---------------------------------------------------------------------------

/** SELECT for blog cards: category + author (team member first, else admin). */
const POST_CARD_SQL = "SELECT b.id, b.title, b.slug, b.excerpt, b.cover_image_id, b.published_at, b.reading_minutes, b.is_featured, b.tags,
                              bc.name AS category_name, bc.slug AS category_slug,
                              COALESCE(tm.name, au.name) AS author_name, tm.photo_id AS author_photo_id, tm.position AS author_position
                       FROM blog_posts b
                       LEFT JOIN blog_categories bc ON bc.id = b.category_id AND bc.status = 'published'
                       LEFT JOIN team_members tm ON tm.id = b.author_team_id AND tm.status = 'published'
                       LEFT JOIN admin_users au ON au.id = b.author_id";

/** SQL condition: published and the publish date has come. */
const POST_LIVE_SQL = "b.status = 'published' AND (b.published_at IS NULL OR b.published_at <= NOW())";

/** Tags "Supply, Tenders" -> [['Supply', 'supply'], ['Tenders', 'tenders']] */
function post_tags(?string $tags): array
{
    $out = [];
    foreach (explode(',', (string) $tags) as $t) {
        $t = trim($t);
        if ($t !== '' && ($slug = slugify($t, 60)) !== '') {
            $out[$slug] = [$t, $slug];
        }
    }
    return array_values($out);
}

function post_date(?string $date): string
{
    return $date ? date('j M Y', strtotime($date)) : '';
}

/** Blog card. $variant 'featured' = large horizontal card. */
function post_card_html(array $p, ?array $cover, string $variant = ''): string
{
    $url = '/blog/' . rawurlencode($p['slug']);
    $meta = '';
    if ($p['category_name']) {
        $meta .= '<span class="badge">' . e($p['category_name']) . '</span>';
    }
    if ($p['published_at']) {
        $meta .= '<time datetime="' . e(date('c', strtotime((string) $p['published_at']))) . '">' . e(post_date($p['published_at'])) . '</time>';
    }
    if ($p['reading_minutes']) {
        $meta .= '<span>' . (int) $p['reading_minutes'] . ' min read</span>';
    }
    return '<article class="post-card' . ($variant ? ' post-card--' . $variant : '') . '" data-reveal-item><a href="' . e($url) . '" class="post-card__link">'
        . '<span class="post-card__media">' . ($cover ? media_img($cover, ['sizes' => $variant === 'featured' ? '(min-width: 1024px) 55vw, 100vw' : '(min-width: 1024px) 30vw, (min-width: 640px) 50vw, 100vw', 'alt' => '']) : '<span class="post-card__placeholder">' . icon_svg('pen', 36) . '</span>') . '</span>'
        . '<span class="post-card__body">'
        . ($variant === 'featured' ? '<span class="eyebrow">Featured</span>' : '')
        . '<span class="post-card__meta">' . $meta . '</span>'
        . '<h3 class="post-card__title">' . e($p['title']) . '</h3>'
        . ($p['excerpt'] ? '<p class="post-card__text">' . e($p['excerpt']) . '</p>' : '')
        . ($p['author_name'] ? '<span class="post-card__author">By ' . e($p['author_name']) . '</span>' : '')
        . '</span></a></article>';
}

/**
 * Add id="…" to every h2/h3 of a post and build a table of contents.
 * Returns [html with ids, [[level, id, text], …]].
 */
function post_toc(string $html): array
{
    if (trim($html) === '' || !class_exists('DOMDocument')) {
        return [$html, []];
    }
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="mt-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $toc = [];
    $used = [];
    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//h2|//h3') as $h) {
        $text = trim($h->textContent);
        if ($text === '') {
            continue;
        }
        $id = slugify($text, 60) ?: 'section';
        $base = $id;
        for ($i = 2; isset($used[$id]); $i++) {
            $id = $base . '-' . $i;
        }
        $used[$id] = true;
        $h->setAttribute('id', $id);
        $toc[] = [strtolower($h->nodeName) === 'h3' ? 3 : 2, $id, $text];
    }
    $root = $doc->getElementById('mt-root');
    $out = '';
    foreach ($root ? $root->childNodes : [] as $child) {
        $out .= $doc->saveHTML($child);
    }
    return [$out !== '' ? $out : $html, $toc];
}

/** Share links (plain links — no third-party scripts). */
function share_links_html(string $url, string $title): string
{
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    $links = [
        ['whatsapp', 'WhatsApp', 'https://wa.me/?text=' . rawurlencode($title . ' ' . $url)],
        ['facebook', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $u],
        ['linkedin', 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u],
        ['x-social', 'X', 'https://x.com/intent/post?url=' . $u . '&text=' . $t],
    ];
    $html = '<div class="share" data-reveal><span class="share__label">Share</span><ul class="share__list">';
    foreach ($links as [$icon, $label, $href]) {
        $html .= '<li><a class="share__btn" href="' . e($href) . '" target="_blank" rel="noopener nofollow" aria-label="Share on ' . e($label) . '">' . icon_svg($icon, 20) . '</a></li>';
    }
    $html .= '<li><button type="button" class="share__btn" data-copy-link="' . e($url) . '" aria-label="Copy link" hidden>' . icon_svg('copy', 20) . '</button></li>';
    return $html . '</ul><span class="share__done" data-copy-done aria-live="polite"></span></div>';
}
