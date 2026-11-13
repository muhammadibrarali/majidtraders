<?php
/** "Page not found" in the website design (loaded by error.php). */
declare(strict_types=1);

http_response_code(404);
$services = site_services();
site_head([
    'title' => 'Page not found – ' . s('company_name', 'Majid Traders'),
    'description' => 'The page you are looking for does not exist or has moved.',
    'path' => '/', 'noindex' => true, 'hero' => true,
]);
site_page_header([
    'title' => 'Page not found', 'eyebrow' => 'Error 404',
    'crumbs' => [['Home', '/'], ['Page not found', '/']],
    'lead' => 'Sorry — the page you are looking for doesn’t exist or has moved. These links will get you back on track.',
    'extra' => '<a class="btn btn--primary btn--lg" href="/">Home ' . icon_svg('arrow-right', 20) . '</a>'
        . '<a class="btn btn--ghost-light btn--lg" href="/projects">Our projects</a>'
        . '<a class="btn btn--ghost-light btn--lg" href="/contact">Contact us</a>',
]);
?>
<?php if ($services): ?>
<section class="section section--tight" aria-labelledby="nf-title">
  <div class="container">
    <?= section_head('Popular pages', 'Maybe you were looking for…', 'nf-title') ?>
    <ul class="card-grid" data-reveal-group>
      <?php $m = site_media(array_column($services, 'image_id')); foreach (array_slice($services, 0, 3) as $svc) { echo service_card_html($svc, $m[(int) $svc['image_id']] ?? null); } ?>
    </ul>
  </div>
</section>
<?php endif; ?>
<?php site_cta(); site_footer();
