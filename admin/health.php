<?php
/**
 * Website health check — /admin/health
 *
 * Requests every important CSS / JS / font / image file from this same
 * server (exactly like a browser would) and shows what came back:
 * HTTP status, file type and caching headers. Readable even if the admin
 * styling itself is broken.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
require ROOT_PATH . '/includes/admin_layout.php';
require_once ROOT_PATH . '/includes/media_lib.php';

$admin = require_admin();

$expect = [
    'css' => 'text/css', 'js' => 'javascript', 'woff2' => 'font/woff2', 'svg' => 'image/svg+xml',
    'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'pdf' => 'application/pdf',
];
$files = [
    '/assets/css/admin.css'      => 'Admin styling',
    '/assets/js/admin.js'        => 'Admin scripts',
    '/assets/css/site.css'       => 'Website styling',
    '/assets/js/site.js'         => 'Website scripts',
    '/assets/css/coming-soon.css' => 'Coming-soon page styling',
    '/assets/fonts/bricolage-grotesque-latin-wght-normal.woff2' => 'Heading font',
    '/assets/fonts/inter-latin-wght-normal.woff2' => 'Body font',
    '/assets/img/favicon.svg'    => 'Browser tab icon',
];
$latest = db()->query("SELECT * FROM media WHERE kind = 'image' ORDER BY id DESC LIMIT 1")->fetch();

$scheme = is_https() ? 'https' : 'http';
$host = preg_replace('/[^a-z0-9.:-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'majidtraders.org'));
$base = $scheme . '://' . $host;

/** Fetch a URL from this server; returns [status, headers(lowercase => value), first bytes, error]. */
function health_fetch(string $url): array
{
    if (!function_exists('curl_init')) {
        return [0, [], '', 'PHP cURL is not available on this server, so the live check cannot run.'];
    }
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_RANGE          => '0-299',
        CURLOPT_USERAGENT      => 'MajidTraders-HealthCheck/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: */*', 'Cache-Control: no-cache'],
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            $p = strpos($line, ':');
            if ($p !== false) {
                $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    $err = $body === false ? curl_error($ch) : '';
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, $headers, is_string($body) ? substr($body, 0, 300) : '', $err];
}

$rows = [];
$problems = 0;
$check = static function (string $path, string $label, ?string $diskFile) use ($base, $expect, &$rows, &$problems): void {
    $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    $url = $base . (str_starts_with($path, '/assets/') ? asset($path) : $path);
    $onDisk = $diskFile === null ? null : (is_file($diskFile) ? filesize($diskFile) : false);
    [$status, $h, $start, $err] = health_fetch($url);
    $type = $h['content-type'] ?? '';
    $looksHtml = (bool) preg_match('/^\s*<(!doctype|html)/i', $start);
    $issues = [];
    if ($onDisk === false) {
        $issues[] = 'The file is MISSING on the server. The last deploy did not copy it — redeploy from hPanel › Git.';
    }
    if ($err !== '') {
        $issues[] = 'Could not request it: ' . $err;
    } elseif (!in_array($status, [200, 206], true)) {
        $issues[] = 'The server answered HTTP ' . $status . ' instead of 200.';
    }
    if ($status && isset($expect[$ext]) && stripos($type, $expect[$ext]) === false) {
        $issues[] = 'Wrong file type “' . ($type ?: 'none') . '” (expected ' . $expect[$ext] . '). Browsers refuse this file.';
    }
    if ($looksHtml && in_array($ext, ['css', 'js'], true)) {
        $issues[] = 'The server sent a web PAGE instead of the file — probably an old error page kept by a cache. Purge the cache (see below).';
    }
    if ($issues) {
        $problems++;
    }
    $rows[] = ['label' => $label, 'url' => $url, 'disk' => $onDisk, 'status' => $status, 'type' => $type,
               'cache' => $h['cache-control'] ?? '', 'via' => implode(' · ', array_filter([
                   isset($h['x-litespeed-cache']) ? 'LiteSpeed cache: ' . $h['x-litespeed-cache'] : '',
                   isset($h['x-hcdn-cache-status']) ? 'Hostinger CDN: ' . $h['x-hcdn-cache-status'] : '',
                   isset($h['x-cache']) ? 'Cache: ' . $h['x-cache'] : '',
                   isset($h['age']) ? 'Age: ' . $h['age'] . 's' : '',
                   isset($h['server']) ? 'Server: ' . $h['server'] : '',
               ])), 'issues' => $issues];
};

foreach ($files as $path => $label) {
    $check($path, $label, ROOT_PATH . $path);
}
if ($latest) {
    $check(media_url($latest, 'sm'), 'Latest Media Library image', media_path($latest, 'sm'));
}

admin_page_start('Website health check', 'dashboard', $admin);
?>
  <section class="card health-summary">
    <?php if ($problems === 0): ?>
      <div class="notice notice--ok" role="status"><p><strong>All files load correctly.</strong> Styling, scripts, fonts and images are delivered with the right file types.</p></div>
    <?php else: ?>
      <div class="notice notice--error" role="alert"><p><strong><?= $problems ?> file<?= $problems === 1 ? '' : 's' ?> with a problem.</strong> See the red rows below.</p></div>
    <?php endif; ?>
    <p class="muted small">Checked from the server at <?= e(date('j M Y, g:i:s a')) ?> · <?= e($base) ?> · PHP <?= e(PHP_VERSION) ?> · <?= e((string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown server')) ?></p>
  </section>

  <section class="card">
    <h2>Files</h2>
    <table class="health-table">
      <thead><tr><th scope="col">File</th><th scope="col">Result</th><th scope="col">Details</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['issues'] ? 'is-bad' : 'is-ok' ?>">
          <th scope="row"><?= e($r['label']) ?><br><small><a href="<?= e($r['url']) ?>" target="_blank" rel="noopener"><?= e(parse_url($r['url'], PHP_URL_PATH)) ?></a></small></th>
          <td><?= $r['issues'] ? '<strong>Problem</strong><br>' . implode('<br>', array_map('e', $r['issues'])) : '<strong>OK</strong>' ?></td>
          <td><small>HTTP <?= (int) $r['status'] ?> · <?= e($r['type'] ?: '—') ?><br>
            On server: <?= $r['disk'] === null ? '—' : ($r['disk'] === false ? 'MISSING' : e(format_bytes((int) $r['disk']))) ?><br>
            Caching: <?= e($r['cache'] ?: '—') ?><?= $r['via'] ? '<br>' . e($r['via']) : '' ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>If something is red</h2>
    <ol>
      <li><strong>MISSING on server:</strong> hPanel › Websites › Manage › Advanced › <strong>Git</strong> › click <strong>Deploy</strong> to copy the latest files again.</li>
      <li><strong>Web page instead of the file, or old file:</strong> clear the server caches: hPanel › Websites › Manage › <strong>Performance › CDN › Purge all cache</strong> (if the CDN is on) and <strong>Advanced › Cache Manager › Purge All</strong>. Then reload this page.</li>
      <li><strong>Wrong file type:</strong> tell Claude the exact text in that row.</li>
    </ol>
    <p><a class="btn btn--ghost" href="/admin/health">Run the check again</a></p>
  </section>
<?php
admin_page_end();
