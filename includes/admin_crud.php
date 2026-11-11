<?php
/**
 * Reusable admin "manager": list page + add/edit form + delete + reorder.
 *
 * A section describes itself with a $resource array and calls crud_run():
 *
 *   crud_run([
 *     'key'       => 'services',             // URL /admin/services + sidebar key
 *     'table'     => 'services',
 *     'singular'  => 'service', 'plural' => 'services', 'label' => 'Services',
 *     'title'     => 'title',                // main name column
 *     'image'     => 'image_id',             // thumbnail column (optional)
 *     'summary'   => 'excerpt',              // short text shown in the list (optional)
 *     'search'    => ['title', 'excerpt'],   // searchable columns
 *     'view_url'  => '/services/{slug}',     // "View on site" (optional)
 *     'seo'       => true,                   // meta_title + meta_description
 *     'fields'    => [ ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true], … ],
 *   ]);
 *
 * Field types: text, slug, textarea, richtext, icon, select, switch, media, gallery.
 * All changes are POST + CSRF; deleting asks for confirmation first.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_layout.php';
require_once __DIR__ . '/admin_form.php';
require_once __DIR__ . '/sanitize.php';

const CRUD_PER_PAGE = 20;

function crud_url(array $r, array $params = []): string
{
    return '/admin/' . $r['key'] . ($params ? '?' . http_build_query($params) : '');
}

function crud_run(array $r): never
{
    $admin = require_admin();
    $r += ['seo' => false, 'image' => null, 'summary' => null, 'view_url' => null, 'search' => [$r['title']]];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        crud_handle_post($r, $admin);
    }

    $action = (string) ($_GET['action'] ?? 'list');
    $id     = (int) ($_GET['id'] ?? 0);
    match ($action) {
        'new'    => crud_form($r, $admin, null),
        'edit'   => crud_form($r, $admin, crud_find($r, $id) ?? crud_not_found($r)),
        'delete' => crud_confirm_delete($r, $admin, crud_find($r, $id) ?? crud_not_found($r)),
        default  => crud_list($r, $admin),
    };
    exit;
}

function crud_find(array $r, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare("SELECT * FROM `{$r['table']}` WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function crud_not_found(array $r): never
{
    flash('error', 'That ' . $r['singular'] . ' was not found. It may have been deleted.');
    redirect(crud_url($r));
}

function crud_view_url(array $r, array $row): ?string
{
    return $r['view_url'] ? str_replace('{slug}', rawurlencode((string) $row['slug']), $r['view_url']) : null;
}

// ---------------------------------------------------------------------------
// POST actions
// ---------------------------------------------------------------------------

function crud_handle_post(array $r, array $admin): never
{
    if (!csrf_check()) {
        flash('error', 'Your session expired, so nothing was changed. Please try again.');
        redirect($_SERVER['REQUEST_URI'] ?? crud_url($r));
    }
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $row = crud_find($r, $id) ?? crud_not_found($r);
        db()->prepare("DELETE FROM `{$r['table']}` WHERE id = ?")->execute([$id]);
        flash('success', '“' . $row[$r['title']] . '” was deleted.');
        redirect(crud_url($r));
    }

    if ($action === 'move') {
        crud_move($r, $id, (string) ($_POST['dir'] ?? '') === 'up' ? -1 : 1);
        redirect(crud_url($r, array_filter(['page' => (int) ($_POST['page'] ?? 1) ?: null])));
    }

    if ($action === 'save') {
        $existing = $id > 0 ? (crud_find($r, $id) ?? crud_not_found($r)) : null;
        [$data, $extra, $errors] = crud_collect($r, $existing);
        if ($errors) {
            // Show the form again with what was typed and the problems.
            crud_form($r, $admin, $existing, $data + $extra, $errors);
            exit;
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($existing) {
                $sets = implode(', ', array_map(static fn ($c) => "`$c` = ?", array_keys($data)));
                $pdo->prepare("UPDATE `{$r['table']}` SET $sets WHERE id = ?")->execute([...array_values($data), $id]);
            } else {
                $cols = implode(', ', array_map(static fn ($c) => "`$c`", array_keys($data)));
                $marks = implode(', ', array_fill(0, count($data), '?'));
                $pdo->prepare("INSERT INTO `{$r['table']}` ($cols) VALUES ($marks)")->execute(array_values($data));
                $id = (int) $pdo->lastInsertId();
            }
            foreach ($r['fields'] as $f) {
                if ($f['type'] === 'gallery') {
                    $pdo->prepare("DELETE FROM `{$f['link_table']}` WHERE `{$f['link_fk']}` = ?")->execute([$id]);
                    $ins = $pdo->prepare("INSERT INTO `{$f['link_table']}` (`{$f['link_fk']}`, media_id, sort_order) VALUES (?, ?, ?)");
                    foreach ($extra[$f['name']] as $i => $mediaId) {
                        $ins->execute([$id, $mediaId, ($i + 1) * 10]);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        $msg = ucfirst($r['singular']) . ' saved.';
        if ($data['status'] === 'draft') {
            $msg .= ' It is a draft, so it is not shown on the website yet.';
        }
        flash('success', $msg);
        redirect(crud_url($r, ['action' => 'edit', 'id' => $id]));
    }

    redirect(crud_url($r));
}

/**
 * Read + validate the submitted form.
 * Returns [column data, extra data (galleries), errors].
 */
function crud_collect(array $r, ?array $existing): array
{
    $data = [];
    $extra = [];
    $errors = [];

    foreach ($r['fields'] as $f) {
        $n = $f['name'];
        $label = $f['label'] ?? $n;
        switch ($f['type']) {
            case 'text':
            case 'textarea':
                $v = post_str($n);
                if (!empty($f['required']) && $v === '') {
                    $errors[$n] = 'Please fill in “' . $label . '”.';
                } elseif (isset($f['max']) && mb_strlen($v) > $f['max']) {
                    $errors[$n] = '“' . $label . '” is too long (maximum ' . $f['max'] . ' characters).';
                }
                $data[$n] = $v === '' ? null : $v;
                break;
            case 'richtext':
                $v = clean_html((string) ($_POST[$n] ?? ''));
                if (!empty($f['required']) && $v === '') {
                    $errors[$n] = 'Please fill in “' . $label . '”.';
                }
                $data[$n] = $v === '' ? null : $v;
                break;
            case 'slug':
                $data[$n] = post_str($n, 200); // checked below, after the title is known
                break;
            case 'icon':
                $v = post_str($n);
                $data[$n] = isset(service_icon_choices()[$v]) ? $v : null;
                break;
            case 'select':
                $v = post_str($n);
                $data[$n] = isset($f['options'][$v]) ? $v : array_key_first($f['options']);
                break;
            case 'switch':
                $data[$n] = isset($_POST[$n]) ? 1 : 0;
                break;
            case 'media':
                $data[$n] = post_media_id($n, $f['accept'] ?? 'image');
                if (!empty($f['required']) && $data[$n] === null) {
                    $errors[$n] = 'Please choose “' . $label . '”.';
                }
                break;
            case 'gallery':
                $extra[$n] = post_gallery_ids($n);
                break;
        }
    }

    // Web address (slug): from the typed value, or made from the title.
    foreach ($r['fields'] as $f) {
        if ($f['type'] !== 'slug') {
            continue;
        }
        $n = $f['name'];
        $typed = $data[$n];
        $slug = slugify($typed !== '' ? $typed : (string) ($data[$f['from'] ?? $r['title']] ?? ''));
        if ($slug === '') {
            $errors[$n] = 'Please enter a web address (letters, numbers and dashes).';
        } else {
            $taken = static function (string $s) use ($r, $existing): ?string {
                $stmt = db()->prepare("SELECT `{$r['title']}` FROM `{$r['table']}` WHERE slug = ? AND id <> ?");
                $stmt->execute([$s, (int) ($existing['id'] ?? 0)]);
                $t = $stmt->fetchColumn();
                return $t === false ? null : (string) $t;
            };
            if ($typed !== '' && ($other = $taken($slug)) !== null) {
                $errors[$n] = 'This web address is already used by “' . $other . '”. Please choose a different one.';
            } elseif ($typed === '') {
                $base = $slug;
                for ($i = 2; $taken($slug) !== null; $i++) {
                    $slug = $base . '-' . $i;
                }
            }
        }
        $data[$n] = $slug;
    }

    if ($r['seo']) {
        $data['meta_title'] = post_str('meta_title') ?: null;
        $data['meta_description'] = post_str('meta_description') ?: null;
        if ($data['meta_title'] !== null && mb_strlen($data['meta_title']) > 160) {
            $errors['meta_title'] = 'The SEO title is too long (maximum 160 characters; about 60 is best).';
        }
        if ($data['meta_description'] !== null && mb_strlen($data['meta_description']) > 300) {
            $errors['meta_description'] = 'The meta description is too long (maximum 300 characters; 120–160 is best).';
        }
    }

    $data['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
    $order = trim((string) ($_POST['sort_order'] ?? ''));
    if ($order !== '' && preg_match('/^-?\d{1,6}$/', $order)) {
        $data['sort_order'] = (int) $order;
    } elseif ($existing) {
        $data['sort_order'] = (int) $existing['sort_order'];
    } else {
        $data['sort_order'] = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `{$r['table']}`")->fetchColumn();
    }
    return [$data, $extra, $errors];
}

/** Move an item one place up (-1) or down (+1) in the list order. */
function crud_move(array $r, int $id, int $dir): void
{
    $pdo = db();
    $ids = array_map('intval', $pdo->query("SELECT id FROM `{$r['table']}` ORDER BY sort_order, id")->fetchAll(PDO::FETCH_COLUMN));
    $pos = array_search($id, $ids, true);
    if ($pos === false || !isset($ids[$pos + $dir])) {
        return;
    }
    [$ids[$pos], $ids[$pos + $dir]] = [$ids[$pos + $dir], $ids[$pos]];
    $stmt = $pdo->prepare("UPDATE `{$r['table']}` SET sort_order = ? WHERE id = ?");
    $pdo->beginTransaction();
    foreach ($ids as $i => $rowId) {
        $stmt->execute([($i + 1) * 10, $rowId]);
    }
    $pdo->commit();
}

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------

function crud_list(array $r, array $admin): void
{
    $q      = trim((string) ($_GET['q'] ?? ''));
    $status = in_array($_GET['status'] ?? '', ['draft', 'published'], true) ? $_GET['status'] : '';
    $page   = max(1, (int) ($_GET['page'] ?? 1));

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(' . implode(' OR ', array_map(static fn ($c) => "t.`$c` LIKE ?", $r['search'])) . ')';
        foreach ($r['search'] as $_) {
            $params[] = '%' . addcslashes($q, '%_\\') . '%';
        }
    }
    if ($status !== '') {
        $where[] = 't.status = ?';
        $params[] = $status;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count = db()->prepare("SELECT COUNT(*) FROM `{$r['table']}` t $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / CRUD_PER_PAGE));
    $page  = min($page, $pages);
    $offset = ($page - 1) * CRUD_PER_PAGE;

    $join = $r['image'] ? "LEFT JOIN media m ON m.id = t.`{$r['image']}`" : '';
    $mediaCols = $r['image'] ? ', m.id AS m_id, m.file_name AS m_file_name, m.kind AS m_kind, m.mime_type AS m_mime_type, m.title AS m_title, m.original_name AS m_original_name, m.alt_text AS m_alt_text' : '';
    $stmt = db()->prepare("SELECT t.*$mediaCols FROM `{$r['table']}` t $join $whereSql ORDER BY t.sort_order, t.id LIMIT " . CRUD_PER_PAGE . " OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $canReorder = $q === '' && $status === '';
    $actions = '<a class="btn" href="' . e(crud_url($r, ['action' => 'new'])) . '">' . icon_svg('plus', 18) . '<span>Add ' . e($r['singular']) . '</span></a>';
    admin_page_start($r['label'], $r['key'], $admin, $actions);
    ?>
  <form class="toolbar" method="get" action="<?= e(crud_url($r)) ?>" role="search">
    <label class="visually-hidden" for="list-q">Search</label>
    <span class="toolbar__search"><?= icon_svg('search', 18) ?><input id="list-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search <?= e($r['plural']) ?>…"></span>
    <label class="visually-hidden" for="list-status">Status</label>
    <select id="list-status" name="status" data-autosubmit>
      <option value="">All statuses</option>
      <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option>
      <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option>
    </select>
    <button class="btn btn--ghost btn--small" type="submit">Filter</button>
    <?php if ($q !== '' || $status !== ''): ?><a class="btn btn--text btn--small" href="<?= e(crud_url($r)) ?>">Clear</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <section class="card empty">
      <div class="empty__icon"><?= icon_svg(admin_menu()[$r['key']][2] ?? 'grid', 28) ?></div>
      <h2><?= $q !== '' || $status !== '' ? 'Nothing matches your search' : 'No ' . e($r['plural']) . ' yet' ?></h2>
      <p class="muted"><?= $q !== '' || $status !== '' ? 'Try a different word or clear the filter.' : 'Add your first one to get started.' ?></p>
      <a class="btn" href="<?= e(crud_url($r, ['action' => 'new'])) ?>"><?= icon_svg('plus', 18) ?><span>Add <?= e($r['singular']) ?></span></a>
    </section>
  <?php else: ?>
    <p class="muted small"><?= $total ?> <?= e($total === 1 ? $r['singular'] : $r['plural']) ?><?= $canReorder ? ' · Use the arrows to change the order shown on the website.' : ' · Clear the search/filter to change the order.' ?></p>
    <ul class="item-list">
      <?php foreach ($rows as $i => $row):
          $thumb = ($r['image'] && $row['m_id']) ? ['id' => $row['m_id'], 'file_name' => $row['m_file_name'], 'kind' => $row['m_kind'], 'mime_type' => $row['m_mime_type'], 'title' => $row['m_title'], 'original_name' => $row['m_original_name'], 'alt_text' => $row['m_alt_text']] : null;
          $view = crud_view_url($r, $row);
          $globalIndex = $offset + $i; ?>
      <li class="item">
        <div class="item__thumb"><?php if ($thumb): ?><img src="<?= e(media_url($thumb, 'sm')) ?>" alt="" loading="lazy"><?php elseif (!empty($row['icon'])): ?><?= icon_svg((string) $row['icon'], 28) ?><?php else: ?><?= icon_svg('image', 24) ?><?php endif; ?></div>
        <div class="item__main">
          <a class="item__title" href="<?= e(crud_url($r, ['action' => 'edit', 'id' => $row['id']])) ?>"><?= e($row[$r['title']]) ?></a>
          <span class="badge badge--<?= $row['status'] === 'published' ? 'published' : 'draft' ?>"><?= $row['status'] === 'published' ? 'Published' : 'Draft' ?></span>
          <?php if ($r['summary'] && !empty($row[$r['summary']])): ?><p class="item__summary"><?= e(mb_strimwidth((string) $row[$r['summary']], 0, 140, '…')) ?></p><?php endif; ?>
          <?php if (isset($row['slug'])): ?><p class="item__slug"><?= e(crud_view_url($r, $row) ?? '/' . $row['slug']) ?></p><?php endif; ?>
        </div>
        <div class="item__actions">
          <?php if ($canReorder): ?>
            <form method="post" action="<?= e(crud_url($r)) ?>" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="page" value="<?= $page ?>">
              <button class="icon-btn" name="dir" value="up" title="Move up"<?= $globalIndex === 0 ? ' disabled' : '' ?>><?= icon_svg('up', 18) ?><span class="visually-hidden">Move up</span></button>
              <button class="icon-btn" name="dir" value="down" title="Move down"<?= $globalIndex === $total - 1 ? ' disabled' : '' ?>><?= icon_svg('down', 18) ?><span class="visually-hidden">Move down</span></button>
            </form>
          <?php endif; ?>
          <a class="btn btn--small btn--ghost" href="<?= e(crud_url($r, ['action' => 'edit', 'id' => $row['id']])) ?>"><?= icon_svg('edit', 16) ?><span>Edit</span></a>
          <?php if ($view && $row['status'] === 'published'): ?><a class="btn btn--small btn--text" href="<?= e($view) ?>" target="_blank" rel="noopener"><?= icon_svg('eye', 16) ?><span>View on site</span></a><?php endif; ?>
          <a class="btn btn--small btn--danger-text" href="<?= e(crud_url($r, ['action' => 'delete', 'id' => $row['id']])) ?>"><?= icon_svg('trash', 16) ?><span>Delete</span></a>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?= pagination_html($page, $pages, static fn (int $p) => crud_url($r, array_filter(['q' => $q, 'status' => $status, 'page' => $p > 1 ? $p : null]))) ?>
  <?php endif;
    admin_page_end();
}

function pagination_html(int $page, int $pages, callable $url): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pages">';
    $html .= $page > 1 ? '<a href="' . e($url($page - 1)) . '">← Previous</a>' : '<span class="is-disabled">← Previous</span>';
    $html .= '<span>Page ' . $page . ' of ' . $pages . '</span>';
    $html .= $page < $pages ? '<a href="' . e($url($page + 1)) . '">Next →</a>' : '<span class="is-disabled">Next →</span>';
    return $html . '</nav>';
}

/** Add / edit form. $values = submitted values when re-showing after errors. */
function crud_form(array $r, array $admin, ?array $row, ?array $values = null, array $errors = []): void
{
    $v = $values ?? $row ?? [];
    $isNew = $row === null;
    $title = $isNew ? 'Add ' . $r['singular'] : 'Edit ' . $r['singular'];
    $back = '<a class="btn btn--text" href="' . e(crud_url($r)) . '">← All ' . e($r['plural']) . '</a>';
    admin_page_start($title, $r['key'], $admin, $back);
    echo form_error_summary($errors);
    ?>
  <form method="post" action="<?= e(crud_url($r, $isNew ? ['action' => 'new'] : ['action' => 'edit', 'id' => $row['id']])) ?>" class="edit-form" data-dirty-check novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><?php endif; ?>

    <div class="edit-form__main">
      <section class="card form">
        <?php foreach ($r['fields'] as $f) {
            echo crud_render_field($f, $v, $row, $errors);
        } ?>
      </section>

      <?php if ($r['seo']): ?>
      <section class="card form">
        <h2>Search engines (SEO)</h2>
        <p class="help">Optional. Leave empty to use the title and short summary automatically.</p>
        <?= field_text('meta_title', 'SEO title', $v['meta_title'] ?? '', ['max' => 160, 'counter' => true, 'recommend' => [30, 60], 'help' => 'The blue headline Google shows. About 50–60 characters is best.'], $errors) ?>
        <?= field_textarea('meta_description', 'Meta description', $v['meta_description'] ?? '', ['max' => 300, 'rows' => 3, 'counter' => true, 'recommend' => [120, 160], 'help' => 'The short text under the headline in Google. 120–160 characters is best.'], $errors) ?>
        <div class="serp" data-serp aria-hidden="true">
          <span class="serp__url">majidtraders.org<?= e(crud_view_url($r, ['slug' => $v['slug'] ?? '']) ?? '') ?></span>
          <span class="serp__title" data-serp-title><?= e(($v['meta_title'] ?? '') ?: ($v[$r['title']] ?? '')) ?></span>
          <span class="serp__desc" data-serp-desc><?= e(($v['meta_description'] ?? '') ?: ($r['summary'] ? ($v[$r['summary']] ?? '') : '')) ?></span>
        </div>
      </section>
      <?php endif; ?>
    </div>

    <aside class="edit-form__side">
      <section class="card form sticky">
        <h2>Publish</h2>
        <?= field_status((string) ($v['status'] ?? 'draft')) ?>
        <?= field_text('sort_order', 'Position in lists', isset($v['sort_order']) ? (string) $v['sort_order'] : '', ['type' => 'number', 'help' => 'Smaller numbers are shown first. Leave empty to add at the end.', 'inputmode' => 'numeric'], $errors) ?>
        <button type="submit" class="btn btn--block"><?= icon_svg('check', 18) ?><span><?= $isNew ? 'Save ' . e($r['singular']) : 'Save changes' ?></span></button>
        <?php if (!$isNew): ?>
          <p class="muted small">Last saved: <?= e(date('j M Y, g:i a', strtotime((string) $row['updated_at']))) ?></p>
          <?php $view = crud_view_url($r, $row); if ($view && $row['status'] === 'published'): ?>
            <a class="btn btn--ghost btn--block" href="<?= e($view) ?>" target="_blank" rel="noopener"><?= icon_svg('eye', 18) ?><span>View on site</span></a>
          <?php endif; ?>
          <a class="btn btn--danger-text btn--block" href="<?= e(crud_url($r, ['action' => 'delete', 'id' => $row['id']])) ?>"><?= icon_svg('trash', 18) ?><span>Delete this <?= e($r['singular']) ?></span></a>
        <?php endif; ?>
      </section>
    </aside>
  </form>
<?php
    admin_page_end();
}

function crud_render_field(array $f, array $v, ?array $row, array $errors): string
{
    $n = $f['name'];
    $label = $f['label'] ?? $n;
    $val = isset($v[$n]) && !is_array($v[$n]) ? (string) $v[$n] : '';
    return match ($f['type']) {
        'text'     => field_text($n, $label, $val, $f, $errors),
        'textarea' => field_textarea($n, $label, $val, $f, $errors),
        'richtext' => field_richtext($n, $label, $val, $f, $errors),
        'slug'     => field_slug($n, $label, $val, $f, $errors),
        'icon'     => field_icon($n, $label, $val, $f, $errors),
        'select'   => field_select($n, $label, $val, $f['options'], $f, $errors),
        'switch'   => field_switch($n, $label, (bool) ($v[$n] ?? false), $f),
        'media'    => field_media($n, $label, $val !== '' ? media_find((int) $val) : null, $f, $errors),
        'gallery'  => field_gallery($n, $label, media_rows(is_array($v[$n] ?? null) ? $v[$n] : crud_gallery_ids($f, $row)), $f, $errors),
        default    => '',
    };
}

function crud_gallery_ids(array $f, ?array $row): array
{
    if (!$row) {
        return [];
    }
    $stmt = db()->prepare("SELECT media_id FROM `{$f['link_table']}` WHERE `{$f['link_fk']}` = ? ORDER BY sort_order, id");
    $stmt->execute([(int) $row['id']]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function crud_confirm_delete(array $r, array $admin, array $row): void
{
    admin_page_start('Delete ' . $r['singular'], $r['key'], $admin);
    ?>
  <section class="card card--narrow confirm">
    <h2>Delete “<?= e($row[$r['title']]) ?>”?</h2>
    <p>This permanently removes the <?= e($r['singular']) ?> from the website and the admin. <strong>This cannot be undone.</strong></p>
    <p class="muted small">Images stay in the Media Library and can be reused.</p>
    <form method="post" action="<?= e(crud_url($r)) ?>" class="confirm__buttons">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
      <button type="submit" class="btn btn--danger"><?= icon_svg('trash', 18) ?><span>Yes, delete it</span></button>
      <a class="btn btn--ghost" href="<?= e(crud_url($r, ['action' => 'edit', 'id' => $row['id']])) ?>">No, keep it</a>
    </form>
  </section>
<?php
    admin_page_end();
}
