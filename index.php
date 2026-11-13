<?php
/**
 * HOME PAGE
 * Everything comes from the database (Site Settings + content sections).
 * Each section is hidden automatically when it has no content.
 * Animations are added by assets/js/site.js; without JavaScript the page is complete.
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require __DIR__ . '/includes/site.php';

site_gate(true);
$pdo = db();

// ---------------------------------------------------------------- data
$clients = $pdo->query("SELECT id, name, logo_id, website_url FROM clients
                        WHERE status = 'published' AND show_in_marquee = 1 ORDER BY sort_order, id")->fetchAll();
$services = site_services();
$projects = $pdo->query("SELECT p.id, p.title, p.slug, p.location, p.year, p.project_status, p.client_department, p.cover_image_id, p.excerpt,
                                CASE WHEN c.status = 'published' THEN c.name END AS client_name
                         FROM projects p LEFT JOIN clients c ON c.id = p.client_id
                         WHERE p.status = 'published' AND p.is_featured = 1 ORDER BY p.sort_order, p.id LIMIT 10")->fetchAll();
if (!$projects) { // nothing marked "feature on home page" yet: show the latest projects
    $projects = $pdo->query("SELECT p.id, p.title, p.slug, p.location, p.year, p.project_status, p.client_department, p.cover_image_id, p.excerpt,
                                    CASE WHEN c.status = 'published' THEN c.name END AS client_name
                             FROM projects p LEFT JOIN clients c ON c.id = p.client_id
                             WHERE p.status = 'published' ORDER BY p.sort_order, p.id LIMIT 6")->fetchAll();
}
$certs = $pdo->query("SELECT id, title, slug, issuing_authority, registration_number, grade, show_number, document_id
                      FROM certifications WHERE status = 'published' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY sort_order, id")->fetchAll();
$testimonials = $pdo->query("SELECT quote, author_name, author_position, organization, photo_id
                             FROM testimonials WHERE status = 'published' ORDER BY sort_order, id")->fetchAll();
$posts = $pdo->query("SELECT b.title, b.slug, b.excerpt, b.cover_image_id, b.published_at, b.reading_minutes, bc.name AS category_name
                      FROM blog_posts b LEFT JOIN blog_categories bc ON bc.id = b.category_id
                      WHERE b.status = 'published' AND (b.published_at IS NULL OR b.published_at <= NOW())
                      ORDER BY b.published_at DESC, b.id DESC LIMIT 3")->fetchAll();
$process = array_values(array_filter((array) json_decode(s('home_process', '[]'), true), static fn ($st) => is_array($st) && trim((string) ($st['title'] ?? '')) !== ''));

// All images in one query
$media = site_media(array_merge(
    [setting_media_id('hero_image_id'), setting_media_id('home_about_image_id'), setting_media_id('logo_id')],
    array_column($clients, 'logo_id'), array_column($services, 'image_id'), array_column($projects, 'cover_image_id'),
    array_column($certs, 'document_id'), array_column($testimonials, 'photo_id'), array_column($posts, 'cover_image_id')
));
$img = static fn ($id): ?array => $id ? ($media[(int) $id] ?? null) : null;
$hero = $img(setting_media_id('hero_image_id'));
$aboutImg = $img(setting_media_id('home_about_image_id'));
$wa = whatsapp_link();

// ---------------------------------------------------------------- SEO
$company = s('company_name', 'Majid Traders');
$social = array_column(social_links(), 2);
$phoneE164 = s('phone') ? substr(tel_link(s('phone')), 4) : null;
$logoM = $img(setting_media_id('logo_id'));
$logoUrl = $logoM ? site_url(media_url($logoM, 'md')) : site_url('/assets/img/favicon.svg');
$street = trim(preg_replace('/,?\s*Mardan,?\s*Khyber Pakhtunkhwa,?\s*Pakistan\s*$/i', '', s('address')) ?? '');
$jsonld = ['@context' => 'https://schema.org', '@graph' => array_values(array_filter([
    array_filter([
        '@type' => 'Organization', '@id' => site_url('/#organization'), 'name' => $company, 'url' => site_url('/'),
        'logo' => $logoUrl, 'slogan' => s('tagline') ?: null, 'email' => s('email') ?: null, 'telephone' => $phoneE164,
        'sameAs' => $social ?: null, 'founder' => s('owner_name') ? ['@type' => 'Person', 'name' => s('owner_name')] : null,
    ]),
    array_filter([
        '@type' => 'LocalBusiness', '@id' => site_url('/#business'), 'name' => $company, 'url' => site_url('/'),
        'description' => s('default_meta_description') ?: null,
        'image' => $hero ? site_url(media_url($hero, 'lg')) : $logoUrl, 'logo' => $logoUrl,
        'telephone' => $phoneE164, 'email' => s('email') ?: null, 'priceRange' => null,
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => $street !== '' ? $street : s('address'), 'addressLocality' => 'Mardan',
                      'addressRegion' => 'Khyber Pakhtunkhwa', 'addressCountry' => 'PK'],
        'areaServed' => 'Khyber Pakhtunkhwa, Pakistan', 'sameAs' => $social ?: null,
        'parentOrganization' => ['@id' => site_url('/#organization')],
    ]),
    ['@type' => 'WebSite', '@id' => site_url('/#website'), 'url' => site_url('/'), 'name' => $company, 'publisher' => ['@id' => site_url('/#organization')]],
]))];

site_head([
    'title'       => s('home_meta_title', $company . ' – ' . s('tagline')),
    'description' => s('default_meta_description'),
    'path'        => '/',
    'active'      => 'home',
    'hero'        => true,
    'preload'     => $hero,
    'og_image'    => null,
    'jsonld'      => $jsonld,
]);

/** Bento layout class for service card $i of $n. */
$bento = static function (int $i, int $n): string {
    if ($n === 1) { return 'bento--full'; }
    if ($i === 0) { return $n === 2 ? 'bento--half' : 'bento--feature'; }
    if ($n === 2) { return 'bento--half'; }
    if ($i <= 2) { return 'bento--side'; }
    $rest = $n - 3; $pos = $i - 3;
    if ($rest % 3 === 1 && $pos === $rest - 1) { return 'bento--full'; }
    if ($rest % 3 === 2 && $pos >= $rest - 2) { return 'bento--half'; }
    return 'bento--third';
};
$headline = s('hero_headline', s('tagline', $company));
?>

<!-- 1. HERO -->
<section class="hero<?= $hero ? '' : ' hero--plain' ?>" aria-labelledby="hero-title">
  <div class="hero__media" data-parallax>
    <?php if ($hero): ?><?= media_img($hero, ['sizes' => '100vw', 'default' => 'lg', 'lazy' => false, 'priority' => true, 'class' => 'hero__img', 'alt' => '']) ?><?php endif; ?>
  </div>
  <div class="hero__shade" aria-hidden="true"></div>
  <div class="container hero__content">
    <p class="eyebrow eyebrow--light hero__eyebrow"><?= e($company) ?> · <?= e(s('address') ? 'Mardan, Khyber Pakhtunkhwa' : 'Pakistan') ?></p>
    <h1 class="hero__title" id="hero-title"><?php foreach (preg_split('/\s+/', trim($headline)) as $w): ?><span class="hero__word"><span><?= e($w) ?></span></span> <?php endforeach; ?></h1>
    <?php if (s('hero_subheadline')): ?><p class="hero__lead"><?= e(s('hero_subheadline')) ?></p><?php endif; ?>
    <div class="hero__actions">
      <?php if (s('hero_button1_text') && s('hero_button1_link')): ?><a class="btn btn--primary btn--lg" href="<?= e(s('hero_button1_link')) ?>"><?= e(s('hero_button1_text')) ?> <?= icon_svg('arrow-right', 20) ?></a><?php endif; ?>
      <?php if (s('hero_button2_text') && s('hero_button2_link')): ?><a class="btn btn--ghost-light btn--lg" href="<?= e(s('hero_button2_link')) ?>"><?= e(s('hero_button2_text')) ?></a><?php endif; ?>
    </div>
  </div>
  <a class="scroll-hint" href="#after-hero"><span class="scroll-hint__line" aria-hidden="true"></span><span>Scroll</span></a>
</section>
<div id="after-hero"></div>

<?php if ($clients): // 2. CLIENT LOGO MARQUEE
    // Repeat the list so the moving row is always wider than the screen.
    $loop = $clients;
    while (count($loop) < 10) { $loop = array_merge($loop, $clients); } ?>
<section class="marquee-section" aria-labelledby="clients-title">
  <div class="container"><h2 class="marquee-section__title" id="clients-title" data-reveal><?= e(s('home_clients_title', 'Our clients')) ?></h2></div>
  <div class="marquee" data-marquee>
    <?php foreach ([false, true] as $copy): ?>
    <ul class="marquee__track"<?= $copy ? ' aria-hidden="true"' : '' ?>>
      <?php foreach ($loop as $c): $logo = $img($c['logo_id']); ?>
        <li class="marquee__item">
          <?php if ($logo): [$lw, $lh] = media_dims($logo, 'sm'); ?>
            <img src="<?= e(media_url($logo, 'sm')) ?>" width="<?= $lw ?>" height="<?= $lh ?>" alt="<?= $copy ? '' : e($c['name']) ?>" loading="lazy" decoding="async">
          <?php else: ?>
            <span class="marquee__name"><?= e($c['name']) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (s('company_intro') || s('home_about_heading')): // 3. ABOUT ?>
<section class="section about" aria-labelledby="about-title">
  <div class="container about__grid">
    <div class="about__text">
      <?= section_head(s('home_about_eyebrow'), s('home_about_heading'), 'about-title') ?>
      <?php if (s('company_intro')): ?><p class="about__intro" data-split-lines><?= e(s('company_intro')) ?></p><?php endif; ?>
      <a class="btn btn--secondary" href="/about" data-reveal>Learn more about us <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <?php if ($aboutImg): ?>
      <figure class="about__media" data-mask-reveal>
        <div class="about__mask"><?= media_img($aboutImg, ['sizes' => '(min-width: 1024px) 40vw, 100vw', 'class' => 'about__img']) ?></div>
      </figure>
    <?php else: ?>
      <div class="about__badge" aria-hidden="true" data-reveal><span>MT</span></div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?= render_counters() // 4. COUNTERS ?>

<?php if ($services): // 5. SERVICES (bento grid) ?>
<section class="section services" aria-labelledby="services-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head(s('home_services_eyebrow'), s('home_services_title', 'Our services'), 'services-title') ?>
      <a class="link-arrow" href="/services" data-reveal>All services <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <ul class="bento" data-reveal-group>
      <?php $n = count($services); foreach ($services as $i => $svc): $cls = $bento($i, $n); $simg = $img($svc['image_id']); $feature = in_array($cls, ['bento--feature', 'bento--full'], true) && $simg; ?>
        <li class="bento__item <?= $cls ?>" data-reveal-item>
          <a class="service-card<?= $feature ? ' service-card--image' : '' ?>" href="/services/<?= e(rawurlencode($svc['slug'])) ?>">
            <?php if ($feature): ?>
              <span class="service-card__bg"><?= media_img($simg, ['sizes' => '(min-width: 1024px) 60vw, 100vw', 'alt' => '']) ?></span>
            <?php elseif ($simg): ?>
              <span class="service-card__thumb"><?= media_img($simg, ['sizes' => '(min-width: 1024px) 30vw, 100vw', 'alt' => '', 'default' => 'sm']) ?></span>
            <?php endif; ?>
            <span class="service-card__body">
              <?php if ($svc['icon']): ?><span class="service-card__icon"><?= icon_svg($svc['icon'], 28) ?></span><?php endif; ?>
              <h3 class="service-card__title"><?= e($svc['title']) ?></h3>
              <?php if ($svc['excerpt']): ?><p class="service-card__text"><?= e($svc['excerpt']) ?></p><?php endif; ?>
              <span class="service-card__more">Learn more <?= icon_svg('arrow-right', 18) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($projects): // 6. FEATURED PROJECTS (pinned horizontal scroll on desktop) ?>
<section class="section projects-showcase" aria-labelledby="projects-title" data-hscroll>
  <div class="container projects-showcase__head">
    <?= section_head(s('home_projects_eyebrow'), s('home_projects_title', 'Our projects'), 'projects-title') ?>
    <a class="btn btn--ghost" href="/projects" data-reveal>View all projects <?= icon_svg('arrow-right', 18) ?></a>
  </div>
  <div class="projects-showcase__viewport">
    <ul class="projects-track" data-hscroll-track data-reveal-group>
      <?php foreach ($projects as $p): $cover = $img($p['cover_image_id']); $client = $p['client_name'] ?: $p['client_department']; ?>
        <li class="project-card" data-reveal-item>
          <a href="/projects/<?= e(rawurlencode($p['slug'])) ?>">
            <span class="project-card__media">
              <?= $cover ? media_img($cover, ['sizes' => '(min-width: 1024px) 34vw, (min-width: 640px) 50vw, 100vw', 'alt' => '']) : '<span class="project-card__placeholder">' . icon_svg('building', 40) . '</span>' ?>
              <?php if ($p['project_status'] === 'ongoing'): ?><span class="badge badge--accent project-card__badge">Ongoing</span><?php endif; ?>
            </span>
            <span class="project-card__body">
              <span class="project-card__meta"><?= e(implode(' · ', array_filter([(string) $p['location'], (string) $p['year']]))) ?></span>
              <h3 class="project-card__title"><?= e($p['title']) ?></h3>
              <?php if ($client): ?><span class="project-card__client"><?= e($client) ?></span><?php endif; ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
      <li class="project-card project-card--more" data-reveal-item>
        <a href="/projects"><span>See every project</span><?= icon_svg('arrow-right', 32) ?></a>
      </li>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($process): // 7. HOW WE WORK ?>
<section class="section process" aria-labelledby="process-title">
  <div class="container process__grid">
    <div class="process__intro">
      <?= section_head(s('home_process_eyebrow'), s('home_process_title', 'How we work'), 'process-title') ?>
    </div>
    <ol class="timeline" data-timeline>
      <span class="timeline__line" aria-hidden="true"><span class="timeline__progress" data-timeline-progress></span></span>
      <?php foreach ($process as $i => $st): ?>
        <li class="timeline__step" data-reveal>
          <span class="timeline__num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h3 class="timeline__title"><?= e($st['title']) ?></h3>
          <?php if (!empty($st['text'])): ?><p class="timeline__text"><?= e($st['text']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<?php if ($certs): // 8. CERTIFICATIONS STRIP ?>
<section class="section section--tight certs" aria-labelledby="certs-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head(s('home_certs_eyebrow'), s('home_certs_title', 'Certifications'), 'certs-title') ?>
      <a class="link-arrow" href="/certifications" data-reveal>All registrations <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <?= render_cert_list($certs) ?>
  </div>
</section>
<?php endif; ?>

<?php if ($testimonials): // 9. TESTIMONIALS SLIDER ?>
<section class="section testimonials" aria-labelledby="testimonials-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head(s('home_testimonials_eyebrow'), s('home_testimonials_title', 'What our clients say'), 'testimonials-title') ?>
      <?php if (count($testimonials) > 1): ?>
      <div class="slider-buttons" data-slider-buttons hidden>
        <button type="button" class="round-btn" data-slider-prev aria-label="Previous testimonial"><?= icon_svg('chevron-left', 22) ?></button>
        <button type="button" class="round-btn" data-slider-next aria-label="Next testimonial"><?= icon_svg('chevron-right', 22) ?></button>
      </div>
      <?php endif; ?>
    </div>
    <div class="slider" data-slider tabindex="0" aria-label="Testimonials (scroll sideways)">
      <?php foreach ($testimonials as $t): $photo = $img($t['photo_id']); ?>
        <figure class="testimonial" data-reveal>
          <?= icon_svg('quote', 34, 'testimonial__mark') ?>
          <blockquote class="testimonial__quote"><p><?= e($t['quote']) ?></p></blockquote>
          <figcaption class="testimonial__by">
            <?php if ($photo): ?><?= media_img($photo, ['default' => 'sm', 'sizes' => '56px', 'class' => 'testimonial__photo', 'alt' => '']) ?><?php endif; ?>
            <span><strong><?= e($t['author_name']) ?></strong><?php $role = implode(', ', array_filter([(string) $t['author_position'], (string) $t['organization']])); if ($role !== ''): ?><span><?= e($role) ?></span><?php endif; ?></span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($posts): // 10. LATEST BLOG POSTS ?>
<section class="section blog-latest" aria-labelledby="blog-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head(s('home_blog_eyebrow'), s('home_blog_title', 'Latest news'), 'blog-title') ?>
      <a class="link-arrow" href="/blog" data-reveal>All posts <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <ul class="post-grid" data-reveal-group>
      <?php foreach ($posts as $post): $cover = $img($post['cover_image_id']); ?>
        <li data-reveal-item>
          <article class="post-card">
            <a href="/blog/<?= e(rawurlencode($post['slug'])) ?>" class="post-card__link">
              <span class="post-card__media"><?= $cover ? media_img($cover, ['sizes' => '(min-width: 1024px) 30vw, (min-width: 640px) 50vw, 100vw', 'alt' => '']) : '<span class="post-card__placeholder">' . icon_svg('pen', 36) . '</span>' ?></span>
              <span class="post-card__body">
                <span class="post-card__meta">
                  <?php if ($post['category_name']): ?><span class="badge"><?= e($post['category_name']) ?></span><?php endif; ?>
                  <?php if ($post['published_at']): ?><time datetime="<?= e(date('c', strtotime((string) $post['published_at']))) ?>"><?= e(date('j M Y', strtotime((string) $post['published_at']))) ?></time><?php endif; ?>
                  <?php if ($post['reading_minutes']): ?><span><?= (int) $post['reading_minutes'] ?> min read</span><?php endif; ?>
                </span>
                <h3 class="post-card__title"><?= e($post['title']) ?></h3>
                <?php if ($post['excerpt']): ?><p class="post-card__text"><?= e($post['excerpt']) ?></p><?php endif; ?>
              </span>
            </a>
          </article>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php site_cta(); // 11. CALL TO ACTION ?>

<?php site_footer();
