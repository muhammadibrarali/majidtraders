<?php
/**
 * PROJECTS — /projects (list with filters + pagination) and /projects/{slug} (detail)
 *
 * Filters are normal URL parameters, so every filtered page can be shared and
 * crawled:  /projects?category=roads-and-bridges&status=ongoing&year=2024&client=nha&page=2
 * JavaScript (site.js) swaps the results smoothly without a full reload.
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/site.php';
site_gate();

const PROJECTS_PER_PAGE = 12;
$company = s('company_name', 'Majid Traders');
$slug = $GLOBALS['route_slug'] ?? null;
$pdo = db();

/** Clean /projects URL for a set of filters + page. */
function projects_url(array $f, int $page = 1): string
{
    $q = array_filter([
        'category' => $f['category'] ?? '', 'status' => $f['status'] ?? '',
        'year' => $f['year'] ?? '', 'client' => $f['client'] ?? '', 'page' => $page > 1 ? (string) $page : '',
    ], static fn ($v) => $v !== '' && $v !== null);
    return '/projects' . ($q ? '?' . http_build_query($q) : '');
}

// =========================================================================
// LIST
// =========================================================================
if ($slug === null) {
    // Only offer filter values that have published projects.
    $cats = $pdo->query("SELECT c.id, c.name, c.slug FROM project_categories c
                         WHERE c.status = 'published' AND EXISTS (SELECT 1 FROM projects p WHERE p.category_id = c.id AND p.status = 'published')
                         ORDER BY c.sort_order, c.name")->fetchAll();
    $clientOpts = $pdo->query("SELECT c.id, c.name, c.slug FROM clients c
                               WHERE c.status = 'published' AND EXISTS (SELECT 1 FROM projects p WHERE p.client_id = c.id AND p.status = 'published')
                               ORDER BY c.name")->fetchAll();
    $years = array_map('intval', $pdo->query("SELECT DISTINCT year FROM projects WHERE status = 'published' AND year IS NOT NULL ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN));
    $statuses = ['completed' => 'Completed', 'ongoing' => 'Ongoing'];

    $catBySlug = array_column($cats, null, 'slug');
    $clientBySlug = array_column($clientOpts, null, 'slug');
    $get = static fn (string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
    $f = [
        'category' => isset($catBySlug[$get('category')]) ? $get('category') : '',
        'status'   => isset($statuses[$get('status')]) ? $get('status') : '',
        'year'     => in_array((int) $get('year'), $years, true) ? (string) (int) $get('year') : '',
        'client'   => isset($clientBySlug[$get('client')]) ? $get('client') : '',
    ];
    $page = max(1, (int) $get('page'));

    // Tidy URLs: "?category=&status=ongoing&year=" (from the form without JavaScript)
    // or unknown values -> one clean, canonical address.
    $clean = projects_url($f, $page);
    $asked = (string) ($_SERVER['REQUEST_URI'] ?? '/projects');
    if ($_GET && $asked !== $clean) {
        header('Location: ' . $clean, true, 301);
        exit;
    }

    $where = ["p.status = 'published'"];
    $params = [];
    if ($f['category'] !== '') { $where[] = 'p.category_id = ?'; $params[] = $catBySlug[$f['category']]['id']; }
    if ($f['status'] !== '')   { $where[] = 'p.project_status = ?'; $params[] = $f['status']; }
    if ($f['year'] !== '')     { $where[] = 'p.year = ?'; $params[] = (int) $f['year']; }
    if ($f['client'] !== '')   { $where[] = 'p.client_id = ?'; $params[] = $clientBySlug[$f['client']]['id']; }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p $whereSql");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    $pages = max(1, (int) ceil($total / PROJECTS_PER_PAGE));
    if ($page > $pages) {
        show_error_page(404); // e.g. ?page=99 — not a real page
    }
    $stmt = $pdo->prepare(PROJECT_CARD_SQL . " $whereSql ORDER BY p.sort_order, p.id LIMIT " . PROJECTS_PER_PAGE . ' OFFSET ' . (($page - 1) * PROJECTS_PER_PAGE));
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    $media = site_media(array_column($projects, 'cover_image_id'));

    // Title / description describe the filter, so each filtered URL is unique.
    $filterWords = array_filter([
        $f['status'] !== '' ? $statuses[$f['status']] : '',
        $f['category'] !== '' ? $catBySlug[$f['category']]['name'] : '',
        $f['year'] !== '' ? $f['year'] : '',
        $f['client'] !== '' ? 'for ' . $clientBySlug[$f['client']]['name'] : '',
    ]);
    $baseTitle = s('projects_page_title', 'Our projects');
    $title = ($filterWords ? 'Projects: ' . implode(' · ', $filterWords) : $baseTitle) . ($page > 1 ? ' – Page ' . $page : '') . ' – ' . $company;
    $desc = $filterWords
        ? 'Projects by ' . $company . ': ' . implode(', ', $filterWords) . '. ' . $total . ' project' . ($total === 1 ? '' : 's') . ' for government and private clients in Khyber Pakhtunkhwa.'
        : s('projects_meta_description', s('default_meta_description'));
    $header = setting_media('projects_page_image_id');
    $path = projects_url($f, $page);
    $crumbs = [['Home', '/'], ['Projects', '/projects']];
    if ($filterWords) {
        $crumbs[] = [implode(' · ', $filterWords), projects_url($f)];
    }
    $hasFilter = (bool) array_filter($f);

    site_head([
        'title' => $title, 'description' => $desc, 'path' => $path, 'active' => 'projects', 'hero' => true,
        'preload' => $header, 'og_image' => $header,
        'prev' => $page > 1 ? projects_url($f, $page - 1) : null, 'next' => $page < $pages ? projects_url($f, $page + 1) : null,
        'jsonld' => page_jsonld($crumbs, [[
            '@type' => 'CollectionPage', 'name' => $filterWords ? 'Projects: ' . implode(' · ', $filterWords) : $baseTitle, 'url' => site_url($path),
            'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $total, 'itemListElement' => array_map(
                static fn ($p, $i) => ['@type' => 'ListItem', 'position' => ($page - 1) * PROJECTS_PER_PAGE + $i + 1, 'url' => site_url('/projects/' . $p['slug']), 'name' => $p['title']],
                $projects, array_keys($projects))],
        ]]),
    ]);
    site_page_header(['title' => $baseTitle, 'crumbs' => $crumbs, 'lead' => s('projects_page_lead'), 'image' => $header, 'eyebrow' => s('home_projects_eyebrow', 'Our work')]);
    ?>
<section class="section section--list" aria-label="Projects">
  <div class="container">
    <?php if ($cats || $clientOpts || count($years) > 1): ?>
    <form class="filters" method="get" action="/projects" data-filters role="search" aria-label="Filter projects">
      <?php if ($cats): ?>
      <div class="filters__field"><label for="flt-category">Category</label>
        <select id="flt-category" name="category"><option value="">All categories</option>
          <?php foreach ($cats as $c): ?><option value="<?= e($c['slug']) ?>"<?= $f['category'] === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <div class="filters__field"><label for="flt-status">Status</label>
        <select id="flt-status" name="status"><option value="">Completed &amp; ongoing</option>
          <?php foreach ($statuses as $k => $v): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <?php if ($years): ?>
      <div class="filters__field"><label for="flt-year">Year</label>
        <select id="flt-year" name="year"><option value="">All years</option>
          <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= $f['year'] === (string) $y ? ' selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <?php if ($clientOpts): ?>
      <div class="filters__field"><label for="flt-client">Client</label>
        <select id="flt-client" name="client"><option value="">All clients</option>
          <?php foreach ($clientOpts as $c): ?><option value="<?= e($c['slug']) ?>"<?= $f['client'] === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <div class="filters__actions">
        <button class="btn btn--secondary" type="submit" data-filters-submit>Show projects</button>
        <a class="filters__clear" href="/projects" data-filters-clear<?= $hasFilter ? '' : ' hidden' ?>>Clear filters</a>
      </div>
    </form>
    <?php endif; ?>

    <div class="results" data-results aria-live="polite">
      <?php if ($projects): ?>
        <p class="results__count"><?= $total ?> project<?= $total === 1 ? '' : 's' ?><?= $pages > 1 ? ' · page ' . $page . ' of ' . $pages : '' ?></p>
        <ul class="projects-grid" data-reveal-group>
          <?php foreach ($projects as $p) { echo project_card_html($p, $media[(int) $p['cover_image_id']] ?? null); } ?>
        </ul>
        <?= site_pagination($page, $pages, static fn (int $n) => projects_url($f, $n)) ?>
      <?php elseif ($hasFilter): ?>
        <?= site_empty('search', 'No projects match these filters', 'Try another category, year or client — or clear the filters to see everything.', 'Clear filters', '/projects') ?>
      <?php else: ?>
        <?= site_empty('building', 'Projects are coming soon', 'We are adding our completed and ongoing work. Contact us to ask about our experience.', 'Contact us', '/contact') ?>
      <?php endif; ?>
    </div>
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
[$p, $isDraft] = site_load('projects', $slug);

$q = static function (string $sql, array $params) use ($pdo): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
};
$client = $p['client_id'] ? ($q("SELECT name, slug, website_url FROM clients WHERE id = ? AND status = 'published'", [$p['client_id']])[0] ?? null) : null;
$cat = $p['category_id'] ? ($q("SELECT name, slug FROM project_categories WHERE id = ? AND status = 'published'", [$p['category_id']])[0] ?? null) : null;
$services = $q("SELECT s.title, s.slug FROM services s JOIN project_services ps ON ps.service_id = s.id
                WHERE ps.project_id = ? AND s.status = 'published' ORDER BY s.sort_order, s.title", [$p['id']]);
$galleryRows = $q('SELECT media_id, caption FROM project_images WHERE project_id = ? ORDER BY sort_order, id', [$p['id']]);

// Previous / next in the published list order
$order = $pdo->query("SELECT id, title, slug FROM projects WHERE status = 'published' ORDER BY sort_order, id")->fetchAll();
$pos = array_search((int) $p['id'], array_map('intval', array_column($order, 'id')), true);
$prev = $pos !== false && $pos > 0 ? $order[$pos - 1] : null;
$next = $pos !== false && $pos < count($order) - 1 ? $order[$pos + 1] : null;

// Related: same category first, then projects sharing a service
$related = $p['category_id'] ? $q(PROJECT_CARD_SQL . " WHERE p.status = 'published' AND p.category_id = ? AND p.id <> ? ORDER BY p.is_featured DESC, p.sort_order, p.id LIMIT 3", [$p['category_id'], $p['id']]) : [];
if (count($related) < 3) {
    $exclude = array_merge([(int) $p['id']], array_map('intval', array_column($related, 'id')));
    $more = $q(PROJECT_CARD_SQL . " WHERE p.status = 'published' AND p.id NOT IN (" . implode(',', array_fill(0, count($exclude), '?')) . ")
               AND p.id IN (SELECT ps.project_id FROM project_services ps WHERE ps.service_id IN (SELECT service_id FROM project_services WHERE project_id = ?))
               ORDER BY p.sort_order, p.id LIMIT " . (3 - count($related)), [...$exclude, $p['id']]);
    $related = array_merge($related, $more);
}

$media = site_media(array_merge([$p['cover_image_id']], array_column($galleryRows, 'media_id'), array_column($related, 'cover_image_id')));
$cover = $media[(int) $p['cover_image_id']] ?? null;
$galleryItems = array_map(static fn ($g) => ['media' => $media[(int) $g['media_id']] ?? null, 'caption' => $g['caption']], $galleryRows);

$path = '/projects/' . $p['slug'];
$crumbs = [['Home', '/'], ['Projects', '/projects']];
if ($cat) {
    $crumbs[] = [$cat['name'], projects_url(['category' => $cat['slug']])];
}
$crumbs[] = [$p['title'], $path];
$desc = $p['meta_description'] ?: ($p['excerpt'] ?: s('projects_meta_description'));
$statusLabel = $p['project_status'] === 'ongoing' ? 'Ongoing' : 'Completed';

site_head([
    'title' => ($p['meta_title'] ?: $p['title']) . ' – ' . $company,
    'description' => $desc, 'path' => $path, 'active' => 'projects', 'hero' => true, 'preload' => $cover, 'og_image' => $cover, 'og_type' => 'article',
    'draft' => $isDraft ? ['edit' => '/admin/projects?action=edit&id=' . $p['id']] : null,
    'jsonld' => page_jsonld($crumbs, [array_filter([
        '@type' => 'CreativeWork', 'name' => $p['title'], 'url' => site_url($path), 'description' => $desc ?: null,
        'image' => $cover ? site_url(media_url($cover, 'lg')) : null, 'dateCreated' => $p['year'] ? (string) $p['year'] : null,
        'locationCreated' => $p['location'] ? ['@type' => 'Place', 'name' => $p['location']] : null,
        'creator' => ['@id' => site_url('/#organization')], 'genre' => $cat['name'] ?? null,
    ])]),
]);
site_page_header([
    'title' => $p['title'], 'crumbs' => $crumbs, 'lead' => (string) $p['excerpt'], 'image' => $cover,
    'eyebrow' => $cat['name'] ?? 'Project',
    'extra' => '<span class="badge ' . ($p['project_status'] === 'ongoing' ? 'badge--accent' : 'badge--light') . '">' . e($statusLabel) . '</span>'
        . ($p['location'] || $p['year'] ? ' <span class="page-hero__meta">' . e(implode(' · ', array_filter([(string) $p['location'], (string) $p['year']]))) . '</span>' : ''),
]);
?>

<section class="section detail" aria-label="Project details">
  <div class="container detail__grid">
    <div class="detail__main">
      <?= site_prose($p['body']) ?: ($p['excerpt'] ? '<p class="lead" data-reveal>' . e($p['excerpt']) . '</p>' : '') ?>
      <?php if ($g = gallery_html($galleryItems, 'Project photos: ' . $p['title'])): ?>
        <h2 class="detail__subtitle" data-reveal>Project photos</h2>
        <?= $g ?>
      <?php endif; ?>
    </div>
    <aside class="detail__aside">
      <div class="facts" data-reveal>
        <h2 class="facts__title">Key facts</h2>
        <dl class="facts__list">
          <?php if ($client): ?><div><dt>Client</dt><dd><a href="<?= e(projects_url(['client' => $client['slug']])) ?>"><?= e($client['name']) ?></a></dd></div><?php endif; ?>
          <?php if ($p['client_department']): ?><div><dt>Department</dt><dd><?= e($p['client_department']) ?></dd></div><?php endif; ?>
          <?php if ($p['location']): ?><div><dt>Location</dt><dd><?= e($p['location']) ?></dd></div><?php endif; ?>
          <?php if ($p['year']): ?><div><dt>Year</dt><dd><a href="<?= e(projects_url(['year' => (string) $p['year']])) ?>"><?= (int) $p['year'] ?></a></dd></div><?php endif; ?>
          <?php if ($cat): ?><div><dt>Category</dt><dd><a href="<?= e(projects_url(['category' => $cat['slug']])) ?>"><?= e($cat['name']) ?></a></dd></div><?php endif; ?>
          <div><dt>Status</dt><dd><span class="badge <?= $p['project_status'] === 'ongoing' ? 'badge--accent' : '' ?>"><?= e($statusLabel) ?></span></dd></div>
          <?php if ($services): ?><div><dt>Services</dt><dd><ul class="facts__tags"><?php foreach ($services as $sv): ?><li><a href="/services/<?= e(rawurlencode($sv['slug'])) ?>"><?= e($sv['title']) ?></a></li><?php endforeach; ?></ul></dd></div><?php endif; ?>
        </dl>
        <a class="btn btn--primary btn--block" href="/contact?project=<?= e(rawurlencode($p['slug'])) ?>">Discuss a similar project <?= icon_svg('arrow-right', 18) ?></a>
      </div>
    </aside>
  </div>
</section>

<?php if ($prev || $next): ?>
<nav class="container prevnext" aria-label="More projects">
  <?php if ($prev): ?><a class="prevnext__link prevnext__link--prev" href="/projects/<?= e(rawurlencode($prev['slug'])) ?>" rel="prev" data-reveal><span class="prevnext__label"><?= icon_svg('arrow-right', 18) ?> Previous project</span><span class="prevnext__title"><?= e($prev['title']) ?></span></a><?php else: ?><span></span><?php endif; ?>
  <?php if ($next): ?><a class="prevnext__link prevnext__link--next" href="/projects/<?= e(rawurlencode($next['slug'])) ?>" rel="next" data-reveal><span class="prevnext__label">Next project <?= icon_svg('arrow-right', 18) ?></span><span class="prevnext__title"><?= e($next['title']) ?></span></a><?php endif; ?>
</nav>
<?php endif; ?>

<?php if ($related): ?>
<section class="section related" aria-labelledby="related-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head('More work', 'Related projects', 'related-title') ?>
      <a class="link-arrow" href="/projects" data-reveal>All projects <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <ul class="projects-grid" data-reveal-group>
      <?php foreach ($related as $r) { echo project_card_html($r, $media[(int) $r['cover_image_id']] ?? null); } ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php site_cta(); site_footer();
