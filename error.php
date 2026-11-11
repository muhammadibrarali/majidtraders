<?php
/** Friendly error page for 403 / 404 / 500. Never shows technical details. */
if (!function_exists('e')) {
    require __DIR__ . '/includes/bootstrap.php';
}

$messages = [
    403 => ['Access denied',  'Sorry, you don’t have permission to view this page.'],
    404 => ['Page not found', 'The page you’re looking for doesn’t exist or has moved.'],
    500 => ['Something went wrong', 'We’re having a temporary problem. Please try again in a few minutes.'],
];
$code = (int) ($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 404));
if (!isset($messages[$code])) {
    $code = 404;
}
[$heading, $text] = $messages[$code];
if (!headers_sent()) {
    http_response_code($code);
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($heading) ?> – Majid Traders</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/coming-soon.css">
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
