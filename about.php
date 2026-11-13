<?php
/**
 * ABOUT PAGE — /about
 * Texts from Site Settings › About page (+ Company tab), counters,
 * certifications and a short team preview. Sections hide when empty.
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require __DIR__ . '/includes/site.php';
site_gate();

$company = s('company_name', 'Majid Traders');
$header  = setting_media('about_page_image_id');
$owner   = setting_media('about_owner_photo_id');
$values  = array_values(array_filter((array) json_decode(s('about_values', '[]'), true), static fn ($v) => is_array($v) && trim((string) ($v['title'] ?? '')) !== ''));
$team    = db()->query("SELECT name, slug, position, photo_id FROM team_members WHERE status = 'published' ORDER BY sort_order, id LIMIT 4")->fetchAll();
$teamImg = site_media(array_column($team, 'photo_id'));
$certsHtml = render_cert_list();
$title   = s('about_page_title', 'About ' . $company);
$crumbs  = [['Home', '/'], ['About', '/about']];

site_head([
    'title'       => $title . ' – ' . $company,
    'description' => s('about_meta_description', s('default_meta_description')),
    'path'        => '/about',
    'active'      => 'about',
    'hero'        => true,
    'preload'     => $header,
    'og_image'    => $header ?: $owner,
    'jsonld'      => page_jsonld($crumbs, [[
        '@type' => 'AboutPage', 'name' => $title, 'url' => site_url('/about'),
        'about' => ['@id' => site_url('/#organization')], 'description' => s('about_meta_description') ?: null,
    ]]),
]);
site_page_header(['title' => $title, 'crumbs' => $crumbs, 'lead' => s('about_page_lead'), 'image' => $header, 'eyebrow' => s('tagline')]);
?>

<?php if (s('company_intro') || s('about_story_body')): ?>
<section class="section story" aria-labelledby="story-title">
  <div class="container story__grid">
    <div class="story__intro">
      <?= section_head(s('home_about_eyebrow', 'About us'), s('about_story_heading', 'Our story'), 'story-title') ?>
      <?php if (s('company_intro')): ?><p class="story__lead" data-split-lines><?= e(s('company_intro')) ?></p><?php endif; ?>
    </div>
    <?= site_prose(s('about_story_body'), 'story__body') ?>
  </div>
</section>
<?php endif; ?>

<?php if (s('about_mission') || s('about_vision')): ?>
<section class="section section--tight mv" aria-label="Mission and vision">
  <div class="container mv__grid" data-reveal-group>
    <?php if (s('about_mission')): ?>
      <article class="mv__card mv__card--dark" data-reveal-item>
        <span class="mv__icon"><?= icon_svg('shield', 30) ?></span>
        <h2 class="mv__title">Our mission</h2>
        <p><?= e(s('about_mission')) ?></p>
      </article>
    <?php endif; ?>
    <?php if (s('about_vision')): ?>
      <article class="mv__card mv__card--accent" data-reveal-item>
        <span class="mv__icon"><?= icon_svg('eye', 30) ?></span>
        <h2 class="mv__title">Our vision</h2>
        <p><?= e(s('about_vision')) ?></p>
      </article>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($values): ?>
<section class="section values" aria-labelledby="values-title">
  <div class="container">
    <?= section_head('What we stand for', 'Our core values', 'values-title') ?>
    <ol class="values__grid" data-reveal-group>
      <?php foreach ($values as $i => $v): ?>
        <li class="value" data-reveal-item>
          <span class="value__num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h3 class="value__title"><?= e($v['title']) ?></h3>
          <?php if (!empty($v['text'])): ?><p class="value__text"><?= e($v['text']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<?php if (s('about_owner_message')): ?>
<section class="section owner" aria-labelledby="owner-title">
  <div class="container owner__grid">
    <?php if ($owner): ?>
      <figure class="owner__media" data-mask-reveal>
        <div class="about__mask"><?= media_img($owner, ['sizes' => '(min-width: 1024px) 34vw, 100vw', 'class' => 'about__img']) ?></div>
      </figure>
    <?php endif; ?>
    <div class="owner__text">
      <?= section_head('Leadership', s('about_owner_heading', 'A message from our founder'), 'owner-title') ?>
      <blockquote class="owner__quote" data-reveal><?= site_paragraphs(s('about_owner_message')) ?></blockquote>
      <?php if (s('owner_name')): ?>
        <p class="owner__sign" data-reveal><strong><?= e(s('owner_name')) ?></strong><?php if (s('about_owner_title')): ?><span><?= e(s('about_owner_title')) ?>, <?= e($company) ?></span><?php endif; ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?= render_counters() ?>

<?php if ($certsHtml): ?>
<section class="section section--tight certs" aria-labelledby="certs-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head(s('home_certs_eyebrow'), s('home_certs_title', 'Certifications'), 'certs-title') ?>
      <a class="link-arrow" href="/certifications" data-reveal>All registrations <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <?= $certsHtml ?>
  </div>
</section>
<?php endif; ?>

<?php if ($team): ?>
<section class="section team-preview" aria-labelledby="team-title">
  <div class="container">
    <div class="section-head-row">
      <?= section_head('Our people', 'The team behind the work', 'team-title') ?>
      <a class="btn btn--ghost" href="/team" data-reveal>Meet the whole team <?= icon_svg('arrow-right', 18) ?></a>
    </div>
    <ul class="team-grid" data-reveal-group>
      <?php foreach ($team as $t): $photo = $teamImg[(int) $t['photo_id']] ?? null; ?>
        <li class="person" data-reveal-item>
          <a href="/team#<?= e($t['slug']) ?>">
            <span class="person__photo"><?= $photo ? media_img($photo, ['sizes' => '(min-width: 1024px) 22vw, 50vw', 'alt' => '']) : '<span class="person__initials">' . e(mb_strtoupper(mb_substr($t['name'], 0, 1))) . '</span>' ?></span>
            <span class="person__name"><?= e($t['name']) ?></span>
            <?php if ($t['position']): ?><span class="person__role"><?= e($t['position']) ?></span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php site_cta(); site_footer();
