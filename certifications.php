<?php
/**
 * CERTIFICATIONS & REGISTRATIONS — /certifications
 * Published, NOT expired certificates. Registration numbers only when
 * "Show registration number publicly" is ticked. Images open in the lightbox,
 * PDFs open in a new tab. Each card has id="<slug>" (/certifications#<slug>).
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/site.php';
site_gate();

$company = s('company_name', 'Majid Traders');
$certs = db()->query("SELECT title, slug, issuing_authority, registration_number, grade, show_number, issue_date, expiry_date, description, document_id
                      FROM certifications WHERE status = 'published' AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                      ORDER BY sort_order, id")->fetchAll();
$media = site_media(array_column($certs, 'document_id'));
$header = setting_media('certifications_page_image_id');
$title = s('certifications_page_title', 'Certifications & registrations');
$crumbs = [['Home', '/'], ['About', '/about'], ['Certifications', '/certifications']];

site_head([
    'title' => $title . ' – ' . $company, 'description' => s('certifications_meta_description', s('default_meta_description')),
    'path' => '/certifications', 'active' => 'about', 'hero' => true, 'preload' => $header, 'og_image' => $header,
    'jsonld' => page_jsonld($crumbs, $certs ? [[
        '@type' => 'Organization', '@id' => site_url('/#organization'), 'name' => $company,
        'hasCredential' => array_map(static fn ($c) => array_filter([
            '@type' => 'EducationalOccupationalCredential', 'name' => $c['title'], 'credentialCategory' => $c['grade'] ? 'Category ' . $c['grade'] : null,
            'recognizedBy' => $c['issuing_authority'] ? ['@type' => 'Organization', 'name' => $c['issuing_authority']] : null,
            'validFrom' => $c['issue_date'] ?: null, 'expires' => $c['expiry_date'] ?: null,
        ]), $certs),
    ]] : []),
]);
site_page_header([
    'title' => $title, 'crumbs' => $crumbs, 'lead' => s('certifications_page_lead'), 'image' => $header, 'eyebrow' => s('home_certs_eyebrow', 'Registered & certified'),
]);
?>

<section class="section" aria-label="Certificates">
  <div class="container">
    <?php if (!$certs): ?>
      <?= site_empty('award', 'Certificates are being added', 'Our registrations and certificates will be listed here soon. Contact us if you need copies for a tender.', 'Contact us', '/contact') ?>
    <?php else: ?>
      <ul class="cert-grid" data-gallery data-reveal-group aria-label="Certificates">
        <?php foreach ($certs as $c): $doc = $media[(int) $c['document_id']] ?? null; ?>
          <li class="cert-card" id="<?= e($c['slug']) ?>" data-reveal-item>
            <div class="cert-card__top">
              <span class="cert__badge"><?= $doc && $doc['kind'] === 'image' ? media_img($doc, ['default' => 'sm', 'sizes' => '64px', 'alt' => '']) : icon_svg('award', 30) ?></span>
              <div>
                <h2 class="cert-card__title"><?= e($c['title']) ?></h2>
                <?php if ($c['issuing_authority']): ?><p class="cert-card__by"><?= e($c['issuing_authority']) ?></p><?php endif; ?>
              </div>
            </div>
            <?php if ($c['grade']): ?><span class="badge badge--accent">Category <?= e($c['grade']) ?></span><?php endif; ?>
            <?php if ($c['description']): ?><p class="cert-card__text"><?= e($c['description']) ?></p><?php endif; ?>
            <dl class="cert-card__facts">
              <?php if ($c['show_number'] && $c['registration_number']): ?><div><dt>Registration no.</dt><dd><?= e($c['registration_number']) ?></dd></div><?php endif; ?>
              <?php if ($c['issue_date']): ?><div><dt>Issued</dt><dd><time datetime="<?= e($c['issue_date']) ?>"><?= e(date('j M Y', strtotime($c['issue_date']))) ?></time></dd></div><?php endif; ?>
              <div><dt>Valid until</dt><dd><?= $c['expiry_date'] ? '<time datetime="' . e($c['expiry_date']) . '">' . e(date('j M Y', strtotime($c['expiry_date']))) . '</time>' : 'No expiry' ?></dd></div>
            </dl>
            <?php if ($doc && $doc['kind'] === 'image'): [$dw, $dh] = media_dims($doc, 'lg'); ?>
              <a class="btn btn--ghost btn--sm cert-card__view" href="<?= e(media_url($doc, 'lg')) ?>" data-lightbox data-width="<?= $dw ?>" data-height="<?= $dh ?>" data-caption="<?= e($c['title']) ?>"><img class="visually-hidden" src="<?= e(media_url($doc, 'sm')) ?>" alt="<?= e((string) $doc['alt_text'] ?: $c['title']) ?>" loading="lazy"><?= icon_svg('eye', 18) ?> View certificate</a>
            <?php elseif ($doc): ?>
              <a class="btn btn--ghost btn--sm cert-card__view" href="<?= e(media_url($doc)) ?>" target="_blank" rel="noopener"><?= icon_svg('file', 18) ?> View certificate (PDF)</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<?php site_cta(); site_footer();
