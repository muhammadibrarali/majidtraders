<?php
/**
 * PRIVACY POLICY — /privacy-policy
 * Text from Site Settings › Legal; contact details added from the Company tab.
 */
declare(strict_types=1);

define('MT_PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/site.php';
site_gate();

$company = s('company_name', 'Majid Traders');
$title = s('privacy_page_title', 'Privacy Policy');
$updated = s('privacy_updated');
$crumbs = [['Home', '/'], [$title, '/privacy-policy']];

site_head([
    'title' => $title . ' – ' . $company, 'description' => s('privacy_meta_description', s('default_meta_description')),
    'path' => '/privacy-policy', 'active' => '', 'hero' => true,
    'jsonld' => page_jsonld($crumbs, [array_filter([
        '@type' => 'WebPage', 'name' => $title, 'url' => site_url('/privacy-policy'),
        'dateModified' => $updated ?: null, 'publisher' => ['@id' => site_url('/#organization')],
    ])]),
]);
site_page_header([
    'title' => $title, 'crumbs' => $crumbs, 'eyebrow' => 'Legal',
    'lead' => $updated ? 'Last updated ' . date('j F Y', strtotime($updated)) : '',
]);
?>

<section class="section legal" aria-label="<?= e($title) ?>">
  <div class="container legal__grid">
    <?= site_prose(s('privacy_body')) ?: site_empty('file', 'This page is being written', 'Please contact us with any privacy questions.', 'Contact us', '/contact') ?>
    <aside class="quote-card legal__contact" data-reveal>
      <h2 class="quote-card__title">Questions about your data?</h2>
      <p>Contact <?= e($company) ?>:</p>
      <ul class="contact-list">
        <?php if (s('email')): ?><li><?= icon_svg('mail', 18) ?><a href="mailto:<?= e(s('email')) ?>"><?= e(s('email')) ?></a></li><?php endif; ?>
        <?php if (s('phone')): ?><li><?= icon_svg('phone', 18) ?><a href="<?= e(tel_link(s('phone'))) ?>"><?= e(s('phone')) ?></a></li><?php endif; ?>
        <?php if (s('address')): ?><li><?= icon_svg('map-pin', 18) ?><span><?= e(s('address')) ?></span></li><?php endif; ?>
      </ul>
      <a class="btn btn--primary btn--block" href="/contact">Contact form <?= icon_svg('arrow-right', 18) ?></a>
    </aside>
  </div>
</section>

<?php site_cta(); site_footer();
