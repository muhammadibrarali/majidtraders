<?php
/**
 * CLIENTS — /clients
 * Published clients grouped by type, with their number of published projects.
 * Each client card has id="<slug>" so /clients#<slug> jumps to it.
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/site.php';
site_gate();

$company = s('company_name', 'Majid Traders');
$clients = db()->query("SELECT c.id, c.name, c.slug, c.department_type, c.logo_id, c.website_url, c.description,
                               (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id AND p.status = 'published') AS project_count
                        FROM clients c WHERE c.status = 'published' ORDER BY c.sort_order, c.name")->fetchAll();
$media = site_media(array_column($clients, 'logo_id'));
$labels = client_type_labels();
$groups = [];
foreach ($clients as $c) {
    $groups[isset($labels[$c['department_type']]) ? $c['department_type'] : 'other'][] = $c;
}
$groups = array_filter(array_replace(array_fill_keys(array_keys($labels), []), $groups));

$header = setting_media('clients_page_image_id');
$title = s('clients_page_title', 'Our clients');
$crumbs = [['Home', '/'], ['Clients', '/clients']];

site_head([
    'title' => $title . ' – ' . $company, 'description' => s('clients_meta_description', s('default_meta_description')),
    'path' => '/clients', 'active' => 'clients', 'hero' => true, 'preload' => $header, 'og_image' => $header,
    'jsonld' => page_jsonld($crumbs, $clients ? [[
        '@type' => 'ItemList', 'name' => $title, 'numberOfItems' => count($clients),
        'itemListElement' => array_map(static fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name']], $clients, array_keys($clients)),
    ]] : []),
]);
site_page_header([
    'title' => $title, 'crumbs' => $crumbs, 'lead' => s('clients_page_lead'), 'image' => $header, 'eyebrow' => 'Trusted partners',
    'extra' => $clients ? '<span class="page-hero__meta">' . count($clients) . ' client' . (count($clients) === 1 ? '' : 's') . ' · ' . count($groups) . ' sector' . (count($groups) === 1 ? '' : 's') . '</span>' : '',
]);
?>

<section class="section" aria-label="Clients by type">
  <div class="container">
    <?php if (!$groups): ?>
      <?= site_empty('handshake', 'Our client list is being updated', 'Please check back soon to see the departments and organisations we work with.', 'See our projects', '/projects') ?>
    <?php else: ?>
      <?php if (count($groups) > 1): ?>
        <nav class="chip-nav" aria-label="Jump to a client type" data-reveal>
          <?php foreach ($groups as $type => $list): ?><a class="chip" href="#type-<?= e($type) ?>"><?= e($labels[$type]) ?> <span><?= count($list) ?></span></a><?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <?php foreach ($groups as $type => $list): ?>
        <section class="client-group" id="type-<?= e($type) ?>" aria-labelledby="h-<?= e($type) ?>">
          <h2 class="client-group__title" id="h-<?= e($type) ?>" data-reveal><?= e($labels[$type]) ?> <span class="client-group__count"><?= count($list) ?></span></h2>
          <ul class="client-grid" data-reveal-group>
            <?php foreach ($list as $c): $logo = $media[(int) $c['logo_id']] ?? null; $n = (int) $c['project_count']; ?>
              <li class="client-card" id="<?= e($c['slug']) ?>" data-reveal-item>
                <span class="client-card__logo">
                  <?php if ($logo): [$lw, $lh] = media_dims($logo, 'sm'); ?>
                    <img src="<?= e(media_url($logo, 'sm')) ?>" width="<?= $lw ?>" height="<?= $lh ?>" alt="<?= e($c['name']) ?> logo" loading="lazy" decoding="async">
                  <?php else: ?>
                    <span class="client-card__initials" aria-hidden="true"><?= e(mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/[\s&]+/', $c['name']) ?: [], 0, 3))))) ?></span>
                  <?php endif; ?>
                </span>
                <h3 class="client-card__name"><?= e($c['name']) ?></h3>
                <?php if ($c['description']): ?><p class="client-card__text"><?= e($c['description']) ?></p><?php endif; ?>
                <p class="client-card__meta">
                  <?php if ($n > 0): ?>
                    <a href="/projects?client=<?= e(rawurlencode($c['slug'])) ?>"><?= $n ?> project<?= $n === 1 ? '' : 's' ?> <?= icon_svg('arrow-right', 16) ?></a>
                  <?php else: ?>
                    <span>Trusted client</span>
                  <?php endif; ?>
                </p>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php site_cta(); site_footer();
