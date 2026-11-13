<?php
/**
 * BLOG
 *   /blog                    all posts (featured post on top), ?page=N
 *   /blog/category/{slug}    posts in a category, ?page=N
 *   /blog/tag/{slug}         posts with a tag, ?page=N
 *   /blog/{slug}             one post
 * router.php sets $GLOBALS['route_blog'] = ['type' => post|category|tag, 'slug' => …].
 * Only published posts whose publish date has come are public (scheduled = hidden).
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/site.php';
site_gate();

const POSTS_PER_PAGE = 9;
$company = s('company_name', 'Majid Traders');
$route = $GLOBALS['route_blog'] ?? ['type' => 'list', 'slug' => ''];
$pdo = db();

// =========================================================================
// ONE POST
// =========================================================================
if ($route['type'] === 'post') {
    [$row, $isDraft] = site_load('blog_posts', $route['slug'], 'post_is_live');
    $stmt = $pdo->prepare(POST_CARD_SQL . ' WHERE b.id = ?');
    $stmt->execute([$row['id']]);
    $post = $stmt->fetch() + $row;

    [$body, $toc] = post_toc((string) $post['body']);
    $showToc = count($toc) >= 3;
    $tags = post_tags($post['tags']);

    $rel = $pdo->prepare(POST_CARD_SQL . ' WHERE ' . POST_LIVE_SQL . ' AND b.id <> ?
                          ORDER BY (b.category_id <=> ?) DESC, b.published_at DESC LIMIT 3');
    $rel->execute([$post['id'], $post['category_id']]);
    $related = $rel->fetchAll();

    $media = site_media(array_merge([$post['cover_image_id'], $post['author_photo_id']], array_column($related, 'cover_image_id')));
    $cover = $media[(int) $post['cover_image_id']] ?? null;
    $authorPhoto = $media[(int) $post['author_photo_id']] ?? null;

    $path = '/blog/' . $post['slug'];
    $url = site_url($path);
    $crumbs = [['Home', '/'], ['Blog', '/blog']];
    if ($post['category_name']) {
        $crumbs[] = [$post['category_name'], '/blog/category/' . $post['category_slug']];
    }
    $crumbs[] = [$post['title'], $path];
    $desc = $post['meta_description'] ?: ($post['excerpt'] ?: s('blog_meta_description'));
    $scheduled = $post['status'] === 'published' && $post['published_at'] && strtotime((string) $post['published_at']) > time();
    $words = str_word_count(strip_tags(str_replace('<', ' <', (string) $post['body'])));

    site_head([
        'title' => ($post['meta_title'] ?: $post['title']) . ' – ' . $company,
        'description' => $desc, 'path' => $path, 'active' => 'blog', 'hero' => true, 'preload' => $cover, 'og_image' => $cover, 'og_type' => 'article',
        'draft' => $isDraft ? ['edit' => '/admin/blog?action=edit&id=' . $post['id'],
                               'label' => $scheduled ? 'Scheduled – not public until ' . date('j M Y, g:i a', strtotime((string) $post['published_at'])) : 'Draft – not public'] : null,
        'jsonld' => page_jsonld($crumbs, [array_filter([
            '@type' => 'BlogPosting', 'headline' => mb_substr($post['title'], 0, 110), 'description' => $desc ?: null,
            'url' => $url, 'mainEntityOfPage' => $url,
            'image' => $cover ? [site_url(media_url($cover, 'lg'))] : null,
            'datePublished' => $post['published_at'] ? date('c', strtotime((string) $post['published_at'])) : null,
            'dateModified' => date('c', strtotime((string) $post['updated_at'])),
            'author' => $post['author_name'] ? array_filter(['@type' => 'Person', 'name' => $post['author_name'], 'jobTitle' => $post['author_position'] ?: null]) : ['@id' => site_url('/#organization')],
            'publisher' => ['@id' => site_url('/#organization')],
            'articleSection' => $post['category_name'] ?: null,
            'keywords' => $tags ? implode(', ', array_column($tags, 0)) : null,
            'wordCount' => $words ?: null,
        ])]),
    ]);
    $meta = implode(' <span aria-hidden="true">·</span> ', array_filter([
        $post['published_at'] ? '<time datetime="' . e(date('c', strtotime((string) $post['published_at']))) . '">' . e(post_date($post['published_at'])) . '</time>' : '',
        $post['reading_minutes'] ? (int) $post['reading_minutes'] . ' min read' : '',
        $post['author_name'] ? 'By ' . e($post['author_name']) : '',
    ]));
    site_page_header([
        'title' => $post['title'], 'crumbs' => $crumbs, 'lead' => (string) $post['excerpt'], 'image' => $cover,
        'eyebrow' => $post['category_name'] ?: 'Blog',
        'extra' => $meta !== '' ? '<p class="page-hero__meta">' . $meta . '</p>' : '',
    ]);
    ?>
<section class="section post" aria-label="Article">
  <div class="container post__grid<?= $showToc ? '' : ' post__grid--single' ?>">
    <?php if ($showToc): ?>
      <aside class="toc" aria-labelledby="toc-title">
        <nav class="toc__inner" data-toc data-reveal>
          <h2 class="toc__title" id="toc-title">On this page</h2>
          <ol>
            <?php foreach ($toc as [$lvl, $id, $text]): ?>
              <li class="toc__item toc__item--h<?= $lvl ?>"><a href="#<?= e($id) ?>"><?= e($text) ?></a></li>
            <?php endforeach; ?>
          </ol>
        </nav>
      </aside>
    <?php endif; ?>
    <article class="post__main">
      <?php if ($cover): ?>
        <figure class="post__cover" data-reveal><?= media_img($cover, ['sizes' => '(min-width: 1024px) 760px, 100vw', 'default' => 'lg']) ?></figure>
      <?php endif; ?>
      <?= $body !== '' ? '<div class="prose prose--post" data-reveal>' . $body . '</div>' : '' ?>
      <?php if ($tags): ?>
        <ul class="tags" aria-label="Tags">
          <?php foreach ($tags as [$label, $tslug]): ?><li><a href="/blog/tag/<?= e($tslug) ?>">#<?= e($label) ?></a></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?= share_links_html($url, $post['title']) ?>
      <?php if ($post['author_name']): ?>
        <div class="author" data-reveal>
          <span class="author__photo"><?= $authorPhoto ? media_img($authorPhoto, ['default' => 'sm', 'sizes' => '72px', 'alt' => '']) : e(mb_strtoupper(mb_substr($post['author_name'], 0, 1))) ?></span>
          <span class="author__text"><span class="author__label">Written by</span><strong><?= e($post['author_name']) ?></strong><?php if ($post['author_position']): ?><span><?= e($post['author_position']) ?>, <?= e($company) ?></span><?php endif; ?></span>
        </div>
      <?php endif; ?>
    </article>
  </div>
</section>

<?php if ($related): ?>
<section class="section related" aria-labelledby="related-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head('Keep reading', 'Related posts', 'related-title') ?>
      <a class="link-arrow" href="/blog" data-reveal>All posts <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <ul class="post-grid" data-reveal-group>
      <?php foreach ($related as $r): ?><li><?= post_card_html($r, $media[(int) $r['cover_image_id']] ?? null) ?></li><?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>
<?php
    site_cta();
    site_footer();
    exit;
}

// =========================================================================
// LISTS (all / category / tag)
// =========================================================================
$page = max(1, (int) ($_GET['page'] ?? 1));
$base = '/blog';
$where = [POST_LIVE_SQL];
$params = [];
$heading = s('blog_page_title', 'News & insights');
$lead = s('blog_page_lead');
$metaDesc = s('blog_meta_description', s('default_meta_description'));
$crumbs = [['Home', '/'], ['Blog', '/blog']];
$isDraftCat = false;
$activeCat = '';

if ($route['type'] === 'category') {
    [$cat, $isDraftCat] = site_load('blog_categories', $route['slug']);
    $base = '/blog/category/' . $cat['slug'];
    $where[] = 'b.category_id = ?';
    $params[] = $cat['id'];
    $heading = $cat['name'];
    $lead = $cat['description'] ?: 'Posts about ' . mb_strtolower($cat['name']) . ' from ' . $company . '.';
    $metaDesc = $cat['description'] ?: $cat['name'] . ' — articles and news from ' . $company . ', Mardan.';
    $crumbs[] = [$cat['name'], $base];
    $activeCat = $cat['slug'];
} elseif ($route['type'] === 'tag') {
    // Tags are stored as text on each post: find the live posts carrying this tag.
    $tagLabel = null;
    $ids = [];
    foreach ($pdo->query('SELECT b.id, b.tags FROM blog_posts b WHERE ' . POST_LIVE_SQL . " AND b.tags IS NOT NULL AND b.tags <> ''") as $r) {
        foreach (post_tags($r['tags']) as [$label, $tslug]) {
            if ($tslug === $route['slug']) {
                $ids[] = (int) $r['id'];
                $tagLabel ??= $label;
            }
        }
    }
    if (!$ids) {
        show_error_page(404);
    }
    $base = '/blog/tag/' . $route['slug'];
    $where[] = 'b.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $params = array_merge($params, $ids);
    $heading = '#' . $tagLabel;
    $lead = 'Posts tagged “' . $tagLabel . '”.';
    $metaDesc = 'Articles tagged ' . $tagLabel . ' — news and insights from ' . $company . ', Mardan.';
    $crumbs[] = ['#' . $tagLabel, $base];
}

$listUrl = static fn (int $n): string => $base . ($n > 1 ? '?page=' . $n : '');
// Only ?page=N (N>1) is a valid parameter: everything else -> clean URL
if ($_GET && ($_SERVER['REQUEST_URI'] ?? '') !== $listUrl($page)) {
    header('Location: ' . $listUrl($page), true, 301);
    exit;
}

// Featured post on top of the main blog page (page 1 only)
$featured = null;
if ($route['type'] === 'list') {
    $featured = $pdo->query(POST_CARD_SQL . ' WHERE ' . POST_LIVE_SQL . ' ORDER BY b.is_featured DESC, b.published_at DESC, b.id DESC LIMIT 1')->fetch() ?: null;
    if ($featured) {
        $where[] = 'b.id <> ?';
        $params[] = $featured['id'];
    }
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM blog_posts b ' . $whereSql);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / POSTS_PER_PAGE));
if ($page > $pages) {
    show_error_page(404);
}
$stmt = $pdo->prepare(POST_CARD_SQL . " $whereSql ORDER BY b.published_at DESC, b.id DESC LIMIT " . POSTS_PER_PAGE . ' OFFSET ' . (($page - 1) * POSTS_PER_PAGE));
$stmt->execute($params);
$posts = $stmt->fetchAll();
$showFeatured = $featured && $page === 1;

$cats = $pdo->query('SELECT c.name, c.slug, COUNT(b.id) AS n FROM blog_categories c
                     JOIN blog_posts b ON b.category_id = c.id AND ' . POST_LIVE_SQL . "
                     WHERE c.status = 'published' GROUP BY c.id, c.name, c.slug, c.sort_order ORDER BY c.sort_order, c.name")->fetchAll();
$media = site_media(array_merge(array_column($posts, 'cover_image_id'), [$featured['cover_image_id'] ?? null]));
$header = setting_media('blog_page_image_id');
$path = $listUrl($page);

site_head([
    'title' => ($route['type'] === 'list' ? $heading : $heading . ' – Blog') . ($page > 1 ? ' – Page ' . $page : '') . ' – ' . $company,
    'description' => $metaDesc, 'path' => $path, 'active' => 'blog', 'hero' => true, 'preload' => $header, 'og_image' => $header,
    'prev' => $page > 1 ? $listUrl($page - 1) : null, 'next' => $page < $pages ? $listUrl($page + 1) : null,
    'draft' => $isDraftCat ? ['edit' => '/admin/blog-categories?action=edit&id=' . $cat['id']] : null,
    'jsonld' => page_jsonld($crumbs, [[
        '@type' => 'Blog', 'name' => $heading, 'url' => site_url($path), 'publisher' => ['@id' => site_url('/#organization')],
        'blogPost' => array_map(static fn ($p) => ['@type' => 'BlogPosting', 'headline' => $p['title'], 'url' => site_url('/blog/' . $p['slug']),
            'datePublished' => $p['published_at'] ? date('c', strtotime((string) $p['published_at'])) : null], array_merge($showFeatured ? [$featured] : [], $posts)),
    ]]),
]);
site_page_header(['title' => $heading, 'crumbs' => $crumbs, 'lead' => $lead, 'image' => $header, 'eyebrow' => s('home_blog_eyebrow', 'News & insights')]);
?>

<section class="section section--list" aria-label="Blog posts">
  <div class="container">
    <?php if ($cats): ?>
      <nav class="chip-nav" aria-label="Blog categories" data-reveal>
        <a class="chip<?= $route['type'] === 'list' ? ' is-active' : '' ?>" href="/blog"<?= $route['type'] === 'list' ? ' aria-current="page"' : '' ?>>All posts</a>
        <?php foreach ($cats as $c): ?>
          <a class="chip<?= $activeCat === $c['slug'] ? ' is-active' : '' ?>" href="/blog/category/<?= e($c['slug']) ?>"<?= $activeCat === $c['slug'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?> <span><?= (int) $c['n'] ?></span></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if ($showFeatured): ?>
      <div class="featured-post"><?= post_card_html($featured, $media[(int) $featured['cover_image_id']] ?? null, 'featured') ?></div>
    <?php endif; ?>

    <?php if ($posts): ?>
      <ul class="post-grid" data-reveal-group>
        <?php foreach ($posts as $p): ?><li><?= post_card_html($p, $media[(int) $p['cover_image_id']] ?? null) ?></li><?php endforeach; ?>
      </ul>
      <?= site_pagination($page, $pages, $listUrl) ?>
    <?php elseif (!$showFeatured): ?>
      <?= $route['type'] === 'list'
          ? site_empty('pen', 'Our first posts are on the way', 'We will share company news, project stories and tender tips here soon.', 'See our projects', '/projects')
          : site_empty('pen', 'No posts here yet', 'There are no published posts in this section yet. Have a look at all our posts instead.', 'All posts', '/blog') ?>
    <?php endif; ?>
  </div>
</section>

<?php site_cta(); site_footer();
