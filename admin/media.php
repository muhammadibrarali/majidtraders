<?php
/**
 * Media Library — /admin/media
 *   list (grid + search + upload), ?action=edit&id=, ?action=delete&id=
 *   ?format=json  -> data for the "Choose from Media Library" picker
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
require ROOT_PATH . '/includes/admin_layout.php';
require ROOT_PATH . '/includes/admin_form.php';

$admin = require_admin();
const MEDIA_PER_PAGE = 24;

// ---------------------------------------------------------------------------
// POST: upload / update / delete
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = wants_json();

    // A POST bigger than the server allows arrives completely empty.
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $msg = 'The upload was too large for the server. Please upload fewer or smaller files (maximum ' . format_bytes(media_max_bytes()) . ' each).';
        $json ? json_response(['ok' => false, 'error' => $msg], 413) : (flash('error', $msg) ?: redirect('/admin/media'));
    }
    if (!csrf_check()) {
        $msg = 'Your session expired, so nothing was changed. Please reload the page and try again.';
        $json ? json_response(['ok' => false, 'error' => $msg], 403) : (flash('error', $msg) ?: redirect('/admin/media'));
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upload') {
        // Normalise single (file) and multiple (files[]) uploads into one list.
        $files = [];
        if (isset($_FILES['file'])) {
            $files[] = $_FILES['file'];
        }
        if (isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
            foreach ($_FILES['files']['name'] as $i => $n) {
                $files[] = ['name' => $n, 'type' => $_FILES['files']['type'][$i], 'tmp_name' => $_FILES['files']['tmp_name'][$i],
                            'error' => $_FILES['files']['error'][$i], 'size' => $_FILES['files']['size'][$i]];
            }
        }
        $files = array_values(array_filter($files, static fn ($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
        if (!$files) {
            $msg = 'Please choose at least one file.';
            $json ? json_response(['ok' => false, 'error' => $msg], 422) : (flash('error', $msg) ?: redirect('/admin/media'));
        }

        $alt = post_str('alt_text', 300);
        $title = post_str('title', 190);
        $ok = 0;
        $last = null;
        foreach (array_slice($files, 0, 20) as $file) {
            $res = media_handle_upload($file, $alt, count($files) === 1 ? $title : '', (int) $admin['id']);
            if ($res['ok']) {
                $ok++;
                $last = $res['media'];
            } else {
                if ($json) {
                    json_response(['ok' => false, 'error' => $res['error']], 422);
                }
                flash('error', $res['error']);
            }
        }
        if ($json) {
            json_response(['ok' => true, 'item' => media_to_array($last)]);
        }
        if ($ok > 0) {
            flash('success', $ok === 1 ? '1 file uploaded.' : $ok . ' files uploaded.');
        }
        redirect('/admin/media');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $media = media_find($id);
    if (!$media) {
        flash('error', 'That file was not found. It may have been deleted.');
        redirect('/admin/media');
    }

    if ($action === 'update') {
        $title = post_str('title');
        $alt = trim(preg_replace('/\s+/', ' ', post_str('alt_text')) ?? '');
        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Please give the file a title.';
        } elseif (mb_strlen($title) > 190) {
            $errors['title'] = 'The title is too long (maximum 190 characters).';
        }
        if ($media['kind'] === 'image' && mb_strlen($alt) < 3) {
            $errors['alt_text'] = 'Please describe the image (alt text is needed for Google and screen readers).';
        } elseif (mb_strlen($alt) > 255) {
            $errors['alt_text'] = 'The alt text is too long (maximum 255 characters).';
        }
        if ($errors) {
            media_edit_page($admin, array_merge($media, ['title' => $title, 'alt_text' => $alt]), $errors);
            exit;
        }
        db()->prepare('UPDATE media SET title = ?, alt_text = ? WHERE id = ?')->execute([$title, $alt !== '' ? $alt : null, $id]);
        flash('success', 'File details saved.' . (media_slug($media) !== slugify($title, 80) ? ' The public link changed with the title; old links still work.' : ''));
        redirect('/admin/media?action=edit&id=' . $id);
    }

    if ($action === 'delete') {
        media_delete($id);
        flash('success', '“' . ($media['title'] ?: $media['original_name']) . '” was deleted.');
        redirect('/admin/media');
    }

    redirect('/admin/media');
}

// ---------------------------------------------------------------------------
// GET pages
// ---------------------------------------------------------------------------
$action = (string) ($_GET['action'] ?? '');
if ($action === 'edit' || $action === 'delete') {
    $media = media_find((int) ($_GET['id'] ?? 0));
    if (!$media) {
        flash('error', 'That file was not found. It may have been deleted.');
        redirect('/admin/media');
    }
    $action === 'edit' ? media_edit_page($admin, $media) : media_delete_page($admin, $media);
    exit;
}

// List / picker data
$q    = trim((string) ($_GET['q'] ?? ''));
$kind = in_array($_GET['kind'] ?? '', ['image', 'document'], true) ? $_GET['kind'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(title LIKE ? OR original_name LIKE ? OR alt_text LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
if ($kind !== '') {
    $where[] = 'kind = ?';
    $params[] = $kind;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = db()->prepare("SELECT COUNT(*) FROM media $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / MEDIA_PER_PAGE));
$page = min($page, $pages);
$stmt = db()->prepare("SELECT * FROM media $whereSql ORDER BY id DESC LIMIT " . MEDIA_PER_PAGE . ' OFFSET ' . (($page - 1) * MEDIA_PER_PAGE));
$stmt->execute($params);
$items = $stmt->fetchAll();

if (wants_json()) {
    json_response(['ok' => true, 'items' => array_map('media_to_array', $items), 'page' => $page, 'pages' => $pages, 'total' => $total]);
}

admin_page_start('Media Library', 'media', $admin);
?>
  <section class="card uploader" data-uploader>
    <form method="post" action="/admin/media" enctype="multipart/form-data" class="form" data-uploader-form>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <label class="dropzone" data-dropzone>
        <?= icon_svg('upload', 32) ?>
        <strong>Drag &amp; drop files here, or click to choose</strong>
        <span class="muted small">JPG, PNG, WebP images or PDF files · up to <?= e(format_bytes(media_max_bytes())) ?> each · several at once</span>
        <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf" data-file-input>
      </label>
      <div class="uploader__fallback" data-uploader-fallback>
        <?= field_text('alt_text', 'Alt text (describe the image)', '', ['max' => 255, 'help' => 'Required for images. Example: “Majid Traders team handing over school furniture in Mardan”. Used for all files in this upload.']) ?>
        <button type="submit" class="btn"><?= icon_svg('upload', 18) ?><span>Upload</span></button>
      </div>
    </form>
    <ul class="upload-queue" data-upload-queue hidden></ul>
    <div class="uploader__actions" data-uploader-actions hidden>
      <button type="button" class="btn" data-upload-start><?= icon_svg('upload', 18) ?><span>Upload all</span></button>
      <button type="button" class="btn btn--text" data-upload-clear>Clear list</button>
    </div>
  </section>

  <form class="toolbar" method="get" action="/admin/media" role="search">
    <label class="visually-hidden" for="media-q">Search</label>
    <span class="toolbar__search"><?= icon_svg('search', 18) ?><input id="media-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by title or alt text…"></span>
    <label class="visually-hidden" for="media-kind">Type</label>
    <select id="media-kind" name="kind" data-autosubmit>
      <option value="">All files</option>
      <option value="image"<?= $kind === 'image' ? ' selected' : '' ?>>Images</option>
      <option value="document"<?= $kind === 'document' ? ' selected' : '' ?>>PDF documents</option>
    </select>
    <button class="btn btn--ghost btn--small" type="submit">Filter</button>
    <?php if ($q !== '' || $kind !== ''): ?><a class="btn btn--text btn--small" href="/admin/media">Clear</a><?php endif; ?>
  </form>

  <?php if (!$items): ?>
    <section class="card empty">
      <div class="empty__icon"><?= icon_svg('image', 28) ?></div>
      <h2><?= $q !== '' || $kind !== '' ? 'Nothing matches your search' : 'No files yet' ?></h2>
      <p class="muted"><?= $q !== '' || $kind !== '' ? 'Try a different word or clear the filter.' : 'Upload photos and PDFs above. They can then be used anywhere on the website.' ?></p>
    </section>
  <?php else: ?>
    <p class="muted small"><?= $total ?> file<?= $total === 1 ? '' : 's' ?></p>
    <ul class="media-grid">
      <?php foreach ($items as $m): ?>
        <li class="media-card">
          <a href="/admin/media?action=edit&amp;id=<?= (int) $m['id'] ?>">
            <span class="media-card__thumb">
              <?php if ($m['kind'] === 'image'): ?><img src="<?= e(media_url($m, 'sm')) ?>" alt="<?= e((string) $m['alt_text']) ?>" loading="lazy" width="<?= (int) $m['width'] ?>" height="<?= (int) $m['height'] ?>">
              <?php else: ?><span class="file-badge"><?= icon_svg('file', 34) ?><span>PDF</span></span><?php endif; ?>
            </span>
            <span class="media-card__title"><?= e($m['title'] ?: $m['original_name']) ?></span>
            <span class="media-card__meta"><?= $m['kind'] === 'image' ? (int) $m['width'] . '×' . (int) $m['height'] . ' · ' : 'PDF · ' ?><?= e(format_bytes((int) $m['size_bytes'])) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?= pagination_html_media($page, $pages, $q, $kind) ?>
  <?php endif;
admin_page_end();

// ---------------------------------------------------------------------------

function pagination_html_media(int $page, int $pages, string $q, string $kind): string
{
    if ($pages <= 1) {
        return '';
    }
    $url = static fn (int $p) => '/admin/media?' . http_build_query(array_filter(['q' => $q, 'kind' => $kind, 'page' => $p > 1 ? $p : null]));
    return '<nav class="pagination" aria-label="Pages">'
        . ($page > 1 ? '<a href="' . e($url($page - 1)) . '">← Previous</a>' : '<span class="is-disabled">← Previous</span>')
        . '<span>Page ' . $page . ' of ' . $pages . '</span>'
        . ($page < $pages ? '<a href="' . e($url($page + 1)) . '">Next →</a>' : '<span class="is-disabled">Next →</span>')
        . '</nav>';
}

function media_edit_page(array $admin, array $m, array $errors = []): void
{
    $site = rtrim((string) config('site.url', 'https://majidtraders.org'), '/');
    $usage = media_usage((int) $m['id']);
    admin_page_start('Edit file', 'media', $admin, '<a class="btn btn--text" href="/admin/media">← Media Library</a>');
    echo form_error_summary($errors);
    ?>
  <div class="edit-form">
    <div class="edit-form__main">
      <section class="card media-preview">
        <?php if ($m['kind'] === 'image'): ?>
          <img src="<?= e(media_url($m, 'md')) ?>" alt="<?= e((string) $m['alt_text']) ?>" width="<?= (int) $m['width'] ?>" height="<?= (int) $m['height'] ?>">
        <?php else: ?>
          <span class="file-badge file-badge--large"><?= icon_svg('file', 56) ?><span>PDF</span></span>
          <a class="btn btn--ghost" href="<?= e(media_url($m)) ?>" target="_blank" rel="noopener"><?= icon_svg('eye', 18) ?><span>Open PDF</span></a>
        <?php endif; ?>
      </section>

      <form method="post" action="/admin/media?action=edit&amp;id=<?= (int) $m['id'] ?>" class="card form" data-dirty-check novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
        <?= field_text('title', 'Title', (string) ($m['title'] ?: $m['original_name']), ['required' => true, 'max' => 190, 'help' => 'For your reference and used in the file’s web address.'], $errors) ?>
        <?php if ($m['kind'] === 'image'): ?>
          <?= field_textarea('alt_text', 'Alt text', (string) $m['alt_text'], ['required' => true, 'max' => 255, 'rows' => 2, 'counter' => true, 'help' => 'Describe what is in the picture, e.g. “New classroom furniture delivered to Government High School Mardan”. Google and screen readers use this.'], $errors) ?>
        <?php endif; ?>
        <button type="submit" class="btn"><?= icon_svg('check', 18) ?><span>Save details</span></button>
      </form>
    </div>

    <aside class="edit-form__side">
      <section class="card">
        <h2>File details</h2>
        <dl class="meta meta--stack">
          <dt>Type</dt><dd><?= $m['kind'] === 'image' ? 'Image (' . e(strtoupper(media_ext($m))) . ')' : 'PDF document' ?></dd>
          <?php if ($m['kind'] === 'image'): ?><dt>Size</dt><dd><?= (int) $m['width'] ?> × <?= (int) $m['height'] ?> px · <?= e(format_bytes((int) $m['size_bytes'])) ?></dd>
          <?php else: ?><dt>Size</dt><dd><?= e(format_bytes((int) $m['size_bytes'])) ?></dd><?php endif; ?>
          <dt>Original file</dt><dd><?= e($m['original_name']) ?></dd>
          <dt>Uploaded</dt><dd><?= e(date('j M Y, g:i a', strtotime((string) $m['created_at']))) ?></dd>
        </dl>
      </section>
      <section class="card">
        <h2>Links</h2>
        <?php $links = $m['kind'] === 'image'
            ? ['Large (1920 px)' => media_url($m), 'Medium (1024 px)' => media_url($m, 'md'), 'Thumbnail (400 px)' => media_url($m, 'sm')]
            : ['PDF' => media_url($m)];
        foreach ($links as $label => $path): $full = $site . $path; ?>
          <div class="copy-row">
            <label for="link-<?= e(slugify($label)) ?>"><?= e($label) ?></label>
            <div class="copy-row__box">
              <input id="link-<?= e(slugify($label)) ?>" type="text" readonly value="<?= e($full) ?>" data-copy-source>
              <button type="button" class="icon-btn" data-copy title="Copy link"><?= icon_svg('copy', 18) ?><span class="visually-hidden">Copy <?= e($label) ?> link</span></button>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
      <section class="card">
        <h2>Used in</h2>
        <?php if ($usage): ?>
          <ul class="usage-list"><?php foreach ($usage as $u): ?><li><a href="<?= e($u['url']) ?>"><?= e($u['label']) ?></a></li><?php endforeach; ?></ul>
        <?php else: ?>
          <p class="muted small">Not used anywhere yet.</p>
        <?php endif; ?>
        <a class="btn btn--danger-text btn--block" href="/admin/media?action=delete&amp;id=<?= (int) $m['id'] ?>"><?= icon_svg('trash', 18) ?><span>Delete this file</span></a>
      </section>
    </aside>
  </div>
<?php
    admin_page_end();
}

function media_delete_page(array $admin, array $m): void
{
    $usage = media_usage((int) $m['id']);
    admin_page_start('Delete file', 'media', $admin);
    ?>
  <section class="card card--narrow confirm">
    <h2>Delete “<?= e($m['title'] ?: $m['original_name']) ?>”?</h2>
    <?php if ($usage): ?>
      <div class="notice notice--error" role="alert">
        <p><strong>Warning: this file is in use.</strong> If you delete it, it will disappear from:</p>
        <ul><?php foreach ($usage as $u): ?><li><a href="<?= e($u['url']) ?>"><?= e($u['label']) ?></a></li><?php endforeach; ?></ul>
      </div>
    <?php else: ?>
      <p>This file is not used anywhere on the website.</p>
    <?php endif; ?>
    <p>The file is removed permanently. <strong>This cannot be undone.</strong></p>
    <form method="post" action="/admin/media" class="confirm__buttons">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
      <button type="submit" class="btn btn--danger"><?= icon_svg('trash', 18) ?><span><?= $usage ? 'Delete anyway' : 'Yes, delete it' ?></span></button>
      <a class="btn btn--ghost" href="/admin/media?action=edit&amp;id=<?= (int) $m['id'] ?>">No, keep it</a>
    </form>
  </section>
<?php
    admin_page_end();
}
