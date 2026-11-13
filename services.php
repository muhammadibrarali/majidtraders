<?php
/**
 * SERVICES — /services (list) and /services/{slug} (detail)
 * router.php sets $GLOBALS['route_slug'] for detail pages.
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/site.php';
site_gate();

$company = s('company_name', 'Majid Traders');
$slug = $GLOBALS['route_slug'] ?? null;

// =========================================================================
// LIST
// =========================================================================
if ($slug === null) {
    $services = site_services();
    $media = site_media(array_column($services, 'image_id'));
    $header = setting_media('services_page_image_id');
    $title = s('services_page_title', 'Our services');
    $crumbs = [['Home', '/'], ['Services', '/services']];
    $list = [];
    foreach ($services as $i => $svc) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => site_url('/services/' . $svc['slug']), 'name' => $svc['title']];
    }
    site_head([
        'title' => $title . ' – ' . $company, 'description' => s('services_meta_description', s('default_meta_description')),
        'path' => '/services', 'active' => 'services', 'hero' => true, 'preload' => $header, 'og_image' => $header,
        'jsonld' => page_jsonld($crumbs, $list ? [['@type' => 'ItemList', 'name' => $title, 'itemListElement' => $list]] : []),
    ]);
    site_page_header(['title' => $title, 'crumbs' => $crumbs, 'lead' => s('services_page_lead'), 'image' => $header, 'eyebrow' => s('home_services_eyebrow', 'What we do')]);
    ?>
<section class="section" aria-label="All services">
  <div class="container">
    <?php if ($services): ?>
      <ul class="card-grid" data-reveal-group>
        <?php foreach ($services as $svc) { echo service_card_html($svc, $media[(int) $svc['image_id']] ?? null); } ?>
      </ul>
    <?php else: ?>
      <?= site_empty('briefcase', 'Our services are being updated', 'Please check back soon, or contact us to discuss what you need.', 'Contact us', '/contact') ?>
    <?php endif; ?>
  </div>
</section>
<?php
    site_cta();
    site_footer();
    exit;
}

// =========================================================================
// DETAIL
// =========================================================================
[$svc, $isDraft] = site_load('services', $slug);
$pdo = db();

$gallery = $pdo->prepare('SELECT media_id, caption FROM service_images WHERE service_id = ? ORDER BY sort_order, id');
$gallery->execute([$svc['id']]);
$galleryRows = $gallery->fetchAll();

$projects = $pdo->prepare(PROJECT_CARD_SQL . " JOIN project_services ps ON ps.project_id = p.id
                           WHERE ps.service_id = ? AND p.status = 'published' ORDER BY p.is_featured DESC, p.sort_order, p.id LIMIT 6");
$projects->execute([$svc['id']]);
$projects = $projects->fetchAll();

$others = array_values(array_filter(site_services(), static fn ($o) => (int) $o['id'] !== (int) $svc['id']));

$media = site_media(array_merge([$svc['image_id']], array_column($galleryRows, 'media_id'), array_column($projects, 'cover_image_id'), array_column($others, 'image_id')));
$cover = $media[(int) $svc['image_id']] ?? null;
$galleryItems = array_map(static fn ($g) => ['media' => $media[(int) $g['media_id']] ?? null, 'caption' => $g['caption']], $galleryRows);

$path = '/services/' . $svc['slug'];
$crumbs = [['Home', '/'], ['Services', '/services'], [$svc['title'], $path]];
$desc = $svc['meta_description'] ?: ($svc['excerpt'] ?: s('services_meta_description'));
$quote = '/contact?service=' . rawurlencode($svc['slug']);
$wa = whatsapp_link('Hello Majid Traders, I would like a quotation for ' . $svc['title'] . '.');

site_head([
    'title' => ($svc['meta_title'] ?: $svc['title']) . ' – ' . $company,
    'description' => $desc, 'path' => $path, 'active' => 'services', 'hero' => true, 'preload' => $cover, 'og_image' => $cover,
    'draft' => $isDraft ? ['edit' => '/admin/services?action=edit&id=' . $svc['id']] : null,
    'jsonld' => page_jsonld($crumbs, [array_filter([
        '@type' => 'Service', 'name' => $svc['title'], 'serviceType' => $svc['title'], 'url' => site_url($path),
        'description' => $desc ?: null, 'image' => $cover ? site_url(media_url($cover, 'lg')) : null,
        'provider' => ['@id' => site_url('/#organization')], 'areaServed' => 'Khyber Pakhtunkhwa, Pakistan',
    ])]),
]);
site_page_header([
    'title' => $svc['title'], 'crumbs' => $crumbs, 'lead' => (string) $svc['excerpt'], 'image' => $cover, 'eyebrow' => 'Service',
    'extra' => '<a class="btn btn--primary btn--lg" href="' . e($quote) . '">Request a quote ' . icon_svg('arrow-right', 20) . '</a>',
]);
?>

<section class="section detail" aria-label="<?= e($svc['title']) ?>">
  <div class="container detail__grid">
    <div class="detail__main">
      <?php if ($svc['icon']): ?><span class="detail__icon" data-reveal><?= icon_svg($svc['icon'], 34) ?></span><?php endif; ?>
      <?= site_prose($svc['body']) ?: '<p class="lead" data-reveal>' . e((string) $svc['excerpt']) . '</p>' ?>
      <?php if ($g = gallery_html($galleryItems, 'Photos: ' . $svc['title'])): ?>
        <h2 class="detail__subtitle" data-reveal>Gallery</h2>
        <?= $g ?>
      <?php endif; ?>
    </div>
    <aside class="detail__aside">
      <div class="quote-card" data-reveal>
        <h2 class="quote-card__title">Need <?= e(mb_strtolower($svc['title'])) ?>?</h2>
        <p>Tell us what you need and we will send a clear, honest quotation.</p>
        <a class="btn btn--primary btn--block" href="<?= e($quote) ?>">Request a quote <?= icon_svg('arrow-right', 18) ?></a>
        <?php if ($wa): ?><a class="btn btn--ghost-light btn--block" href="<?= e($wa) ?>" rel="noopener" target="_blank"><?= icon_svg('whatsapp', 18) ?> Ask on WhatsApp</a><?php endif; ?>
        <?php if (s('phone')): ?><a class="quote-card__phone" href="<?= e(tel_link(s('phone'))) ?>"><?= icon_svg('phone', 18) ?> <?= e(s('phone')) ?></a><?php endif; ?>
      </div>
    </aside>
  </div>
</section>

<?php if ($projects): ?>
<section class="section related" aria-labelledby="related-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head('Our work', 'Projects in ' . $svc['title'], 'related-title') ?>
      <a class="link-arrow" href="/projects" data-reveal>All projects <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <ul class="projects-grid" data-reveal-group>
      <?php foreach ($projects as $p) { echo project_card_html($p, $media[(int) $p['cover_image_id']] ?? null); } ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($others): ?>
<section class="section section--tight others" aria-labelledby="others-title">
  <div class="container">
    <?= section_head('Explore', 'Other services', 'others-title') ?>
    <ul class="card-grid" data-reveal-group>
      <?php foreach (array_slice($others, 0, 3) as $o) { echo service_card_html($o, $media[(int) $o['image_id']] ?? null); } ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php site_cta(); site_footer();
