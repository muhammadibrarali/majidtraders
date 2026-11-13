<?php
/**
 * "New website coming soon" page.
 * Shown to normal visitors on the home page while Site Settings ›
 * Website status › "Website is live" is OFF (see site_gate()).
 */
declare(strict_types=1);

// Never let a browser, LiteSpeed or the Hostinger CDN keep this page:
// when the website goes live (or the admin logs in) the new site must show at once.
if (!headers_sent()) {
    header('Cache-Control: no-store, max-age=0');
    header('X-LiteSpeed-Cache-Control: no-cache');
}

$title       = 'Majid Traders – New website coming soon';
$description = 'Majid Traders, Mardan (KP, Pakistan): import & export, government & private contracting, and general order supply. Our new website is coming soon.';
$canonical   = 'https://majidtraders.org/';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="theme-color" content="#1E2124">

<meta property="og:type" content="website">
<meta property="og:site_name" content="Majid Traders">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:locale" content="en_PK">
<meta name="twitter:card" content="summary">

<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('/assets/css/coming-soon.css')) ?>">

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Majid Traders",
  "url": "https://majidtraders.org/",
  "logo": "https://majidtraders.org/assets/img/favicon.svg",
  "slogan": "Live the Trader's Life",
  "description": "Import & export, government & private contracting, and general order supply.",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Mardan",
    "addressRegion": "Khyber Pakhtunkhwa",
    "addressCountry": "PK"
  }
}
</script>
</head>
<body>
<main class="cs">
  <div class="cs__inner">
    <div class="cs__logo" aria-hidden="true">MT</div>

    <h1 class="cs__title">Majid Traders</h1>
    <p class="cs__lead">New website <span class="cs__accent">coming soon</span></p>
    <p class="cs__tagline">Live the Trader’s Life</p>

    <ul class="cs__services" aria-label="What we do">
      <li>Import &amp; Export</li>
      <li>Government &amp; Private Contracting</li>
      <li>General Order Supply</li>
    </ul>
  </div>

  <footer class="cs__footer">
    <p>© <?= date('Y') ?> Majid Traders · Mardan, Khyber Pakhtunkhwa, Pakistan</p>
    <p><a href="https://portal.majidtraders.org" rel="noopener">Staff Login</a></p>
  </footer>
</main>
</body>
</html>
