<?php
/**
 * TEAM — /team
 * Members marked "Shown on website" (status = published), grouped by department.
 * Each card has id="<slug>" so /team#<slug> jumps to it.
 * No phone numbers or home addresses are ever stored or shown.
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/site.php';
site_gate();

$company = s('company_name', 'Majid Traders');
$team = db()->query("SELECT name, slug, position, department, bio, photo_id, linkedin_url
                     FROM team_members WHERE status = 'published' ORDER BY sort_order, id")->fetchAll();
$media = site_media(array_column($team, 'photo_id'));

$order = team_departments();
$groups = [];
foreach ($team as $t) {
    $groups[$t['department'] ?: 'Our team'][] = $t;
}
uksort($groups, static function ($a, $b) use ($order) {
    $ia = array_search($a, $order, true);
    $ib = array_search($b, $order, true);
    return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
});

$header = setting_media('team_page_image_id');
$title = s('team_page_title', 'Our team');
$crumbs = [['Home', '/'], ['About', '/about'], ['Team', '/team']];

site_head([
    'title' => $title . ' – ' . $company, 'description' => s('team_meta_description', s('default_meta_description')),
    'path' => '/team', 'active' => 'about', 'hero' => true, 'preload' => $header, 'og_image' => $header,
    'jsonld' => page_jsonld($crumbs, array_map(static fn ($t) => array_filter([
        '@type' => 'Person', 'name' => $t['name'], 'jobTitle' => $t['position'] ?: null,
        'worksFor' => ['@id' => site_url('/#organization')], 'sameAs' => $t['linkedin_url'] ?: null,
        'url' => site_url('/team#' . $t['slug']),
    ]), $team)),
]);
site_page_header([
    'title' => $title, 'crumbs' => $crumbs, 'lead' => s('team_page_lead'), 'image' => $header, 'eyebrow' => 'Our people',
    'extra' => $team ? '<span class="page-hero__meta">' . count($team) . ' people · ' . count($groups) . ' department' . (count($groups) === 1 ? '' : 's') . '</span>' : '',
]);
?>

<section class="section" aria-label="Team members by department">
  <div class="container">
    <?php if (!$groups): ?>
      <?= site_empty('users', 'Meet our team soon', 'We are adding the people behind Majid Traders. In the meantime, feel free to get in touch.', 'Contact us', '/contact') ?>
    <?php else: ?>
      <?php foreach ($groups as $dept => $list): $did = 'dept-' . slugify($dept); ?>
        <section class="team-group" aria-labelledby="<?= e($did) ?>">
          <h2 class="client-group__title" id="<?= e($did) ?>" data-reveal><?= e($dept) ?> <span class="client-group__count"><?= count($list) ?></span></h2>
          <ul class="team-grid team-grid--full" data-reveal-group>
            <?php foreach ($list as $t): $photo = $media[(int) $t['photo_id']] ?? null; $bioId = 'bio-' . $t['slug']; $long = mb_strlen((string) $t['bio']) > 140; ?>
              <li class="member" id="<?= e($t['slug']) ?>" data-reveal-item>
                <span class="person__photo"><?= $photo ? media_img($photo, ['sizes' => '(min-width: 1024px) 22vw, (min-width: 640px) 33vw, 50vw', 'alt' => 'Photo of ' . $t['name']]) : '<span class="person__initials" aria-hidden="true">' . e(mb_strtoupper(mb_substr($t['name'], 0, 1))) . '</span>' ?></span>
                <div class="member__head">
                  <h3 class="person__name"><?= e($t['name']) ?></h3>
                  <?php if ($t['linkedin_url']): ?><a class="member__in" href="<?= e($t['linkedin_url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($t['name']) ?> on LinkedIn"><?= icon_svg('linkedin', 20) ?></a><?php endif; ?>
                </div>
                <?php if ($t['position']): ?><p class="person__role"><?= e($t['position']) ?></p><?php endif; ?>
                <?php if ($t['bio']): ?>
                  <p class="member__bio<?= $long ? ' is-clamped' : '' ?>" id="<?= e($bioId) ?>"><?= e($t['bio']) ?></p>
                  <?php if ($long): ?><button type="button" class="member__more" data-bio-toggle aria-controls="<?= e($bioId) ?>" aria-expanded="false" hidden>Read more</button><?php endif; ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php site_cta(); site_footer();
