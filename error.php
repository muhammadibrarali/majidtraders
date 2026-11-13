<?php
/** Friendly error page for 403 / 404 / 500. Never shows technical details. */
require_once __DIR__ . '/includes/helpers.php';

$messages = [
    403 => ['Access denied',  'Sorry, you don’t have permission to view this page.'],
    404 => ['Page not found', 'The page you’re looking for doesn’t exist or has moved.'],
    500 => ['Something went wrong', 'We’re having a temporary problem. Please try again in a few minutes.'],
    503 => ['We’ll be right back', 'The website is being set up or updated. Please try again in a few minutes.'],
];
$code = (int) ($GLOBALS['error_code'] ?? $_GET['code'] ?? $_SERVER['REDIRECT_STATUS'] ?? 404);
if (!isset($messages[$code])) {
    $code = 404;
}
[$heading, $text] = $messages[$code];
if (!headers_sent()) {
    http_response_code($code);
    header('Cache-Control: no-store'); // never let a cache keep an error page
}

// Public "Page not found" in the website design (when the website is live or
// the admin is previewing). Admin, media and asset addresses keep this simple page.
if ($code === 404 && empty($GLOBALS['mt_in_404'])) {
    $reqPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (!preg_match('#^/(admin|media|assets|includes|lib)(/|$)#', $reqPath) && is_file(PRIVATE_PATH . '/config.php')) {
        $GLOBALS['mt_in_404'] = true;
        try {
            if (!function_exists('site_head')) {
                if (!defined('MT_PUBLIC_PAGE') && !function_exists('db')) {
                    define('MT_PUBLIC_PAGE', true);
                }
                require_once ROOT_PATH . '/includes/site.php';
            }
            if (site_is_live() || site_is_preview()) {
                require ROOT_PATH . '/includes/not_found.php';
                exit;
            }
        } catch (Throwable $ex) {
            error_log('[majidtraders] 404 page fallback: ' . $ex->getMessage());
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($heading) ?> – Majid Traders</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('/assets/css/coming-soon.css')) ?>">
</head>
<body>
<main class="cs">
  <div class="cs__inner">
    <p class="cs__eyebrow">Error <?= $code ?></p>
    <h1 class="cs__title"><?= e($heading) ?></h1>
    <p class="cs__lead"><?= e($text) ?></p>
    <p><a class="cs__btn" href="/">Back to home</a></p>
  </div>
</main>
</body>
</html>
