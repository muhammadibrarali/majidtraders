<?php
/**
 * Messages inbox — /admin/messages
 * Contact-form messages: list (unread/read, search), open, mark read/unread,
 * delete (with confirmation), export to CSV. All changes POST + CSRF.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
require ROOT_PATH . '/includes/admin_layout.php';
require ROOT_PATH . '/includes/admin_form.php';

$admin = require_admin();
const MSG_PER_PAGE = 25;

/** Current list filters, from GET (or the hidden fields of a POST). */
function msg_filters(array $src): array
{
    return [
        'q'    => mb_substr(trim((string) ($src['q'] ?? '')), 0, 100),
        'show' => in_array($src['show'] ?? '', ['unread', 'read'], true) ? $src['show'] : '',
    ];
}

/** WHERE clause + params for the filters. Unread = status "new". */
function msg_where(array $f): array
{
    $where = [];
    $params = [];
    if ($f['show'] === 'unread') {
        $where[] = "status = 'new'";
    } elseif ($f['show'] === 'read') {
        $where[] = "status <> 'new'";
    }
    if ($f['q'] !== '') {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ? OR subject LIKE ? OR message LIKE ?)';
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function msg_list_url(array $f, array $extra = []): string
{
    $p = array_filter($f + $extra, static fn ($v) => $v !== '' && $v !== null);
    return '/admin/messages' . ($p ? '?' . http_build_query($p) : '');
}

/** Selected IDs from checkboxes (ids[]) or a single id. */
function msg_ids(): array
{
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    if (!empty($_POST['id'])) {
        $ids[] = (int) $_POST['id'];
    }
    return array_values(array_unique(array_filter($ids, static fn ($i) => $i > 0)));
}

/** Spreadsheet-safe CSV cell (stops "=…" formulas from running in Excel). */
function csv_cell(?string $v): string
{
    $v = (string) $v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

// ---------------------------------------------------------------------------
// POST actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = msg_filters($_POST);
    if (!csrf_check()) {
        flash('error', 'Your session expired, so nothing was changed. Please try again.');
        redirect(msg_list_url($f));
    }
    $action = (string) ($_POST['action'] ?? '');
    $ids = msg_ids();
    $in = $ids ? implode(',', array_fill(0, count($ids), '?')) : '';
    $back = !empty($_POST['return_to_message']) && count($ids) === 1 ? '/admin/messages?id=' . $ids[0] : msg_list_url($f);

    if ($action === 'export') {
        [$whereSql, $params] = msg_where($f);
        $stmt = db()->prepare("SELECT * FROM contact_messages $whereSql ORDER BY created_at DESC, id DESC");
        $stmt->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="majidtraders-messages-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF"); // so Excel shows Urdu/special characters correctly
        fputcsv($out, ['Date', 'Name', 'Email', 'Phone', 'Company', 'Subject', 'Message', 'Status'], ',', '"', '\\');
        foreach ($stmt as $m) {
            fputcsv($out, [
                $m['created_at'], csv_cell($m['name']), csv_cell($m['email']), csv_cell($m['phone']), csv_cell($m['company']),
                csv_cell($m['subject']), csv_cell($m['message']), $m['status'] === 'new' ? 'Unread' : 'Read',
            ], ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    if (!$ids) {
        flash('error', 'Please tick at least one message first.');
        redirect(msg_list_url($f));
    }

    if ($action === 'mark_read' || $action === 'mark_unread') {
        $stmt = db()->prepare("UPDATE contact_messages SET status = ? WHERE id IN ($in)");
        $stmt->execute([$action === 'mark_read' ? 'read' : 'new', ...$ids]);
        flash('success', count($ids) . ' message' . (count($ids) === 1 ? '' : 's') . ' marked as ' . ($action === 'mark_read' ? 'read.' : 'unread.'));
        redirect($action === 'mark_unread' ? msg_list_url($f) : $back);
    }

    if ($action === 'delete') {
        if (empty($_POST['confirmed'])) {
            msg_confirm_delete_page($admin, $ids, $f);
            exit;
        }
        $stmt = db()->prepare("DELETE FROM contact_messages WHERE id IN ($in)");
        $stmt->execute($ids);
        flash('success', $stmt->rowCount() . ' message' . ($stmt->rowCount() === 1 ? ' was' : 's were') . ' deleted.');
        redirect(msg_list_url($f));
    }
    redirect(msg_list_url($f));
}

// ---------------------------------------------------------------------------
// One message
// ---------------------------------------------------------------------------
if (isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM contact_messages WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $m = $stmt->fetch();
    if (!$m) {
        flash('error', 'That message was not found. It may have been deleted.');
        redirect('/admin/messages');
    }
    if ($m['status'] === 'new') {
        db()->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?")->execute([$m['id']]);
    }
    $subject = $m['subject'] ?: 'Your message to Majid Traders';
    $reply = 'mailto:' . rawurlencode((string) $m['email']) . '?subject=' . rawurlencode('Re: ' . $subject);
    admin_page_start('Message', 'messages', $admin, '<a class="btn btn--text" href="/admin/messages">← Inbox</a>');
    ?>
  <div class="edit-form">
    <div class="edit-form__main">
      <article class="card message">
        <h2 class="message__subject"><?= e($subject) ?></h2>
        <p class="muted small">Received <?= e(date('l j F Y, g:i a', strtotime((string) $m['created_at']))) ?></p>
        <div class="message__body"><?= nl2br(e($m['message'])) ?></div>
      </article>
    </div>
    <aside class="edit-form__side">
      <section class="card">
        <h2>From</h2>
        <dl class="meta meta--stack">
          <dt>Name</dt><dd><?= e($m['name']) ?></dd>
          <dt>Email</dt><dd><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></dd>
          <?php if ($m['phone']): ?><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $m['phone'])) ?>"><?= e($m['phone']) ?></a></dd><?php endif; ?>
          <?php if ($m['company']): ?><dt>Company</dt><dd><?= e($m['company']) ?></dd><?php endif; ?>
        </dl>
        <a class="btn btn--block" href="<?= e($reply) ?>"><?= icon_svg('mail', 18) ?><span>Reply by email</span></a>
        <form method="post" action="/admin/messages" class="stack-form">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
          <button class="btn btn--ghost btn--block" name="action" value="mark_unread"><?= icon_svg('mail', 18) ?><span>Mark as unread</span></button>
          <button class="btn btn--danger-text btn--block" name="action" value="delete"><?= icon_svg('trash', 18) ?><span>Delete message</span></button>
        </form>
      </section>
    </aside>
  </div>
<?php
    admin_page_end();
    exit;
}

// ---------------------------------------------------------------------------
// Inbox list
// ---------------------------------------------------------------------------
$f = msg_filters($_GET);
$page = max(1, (int) ($_GET['page'] ?? 1));
[$whereSql, $params] = msg_where($f);
$stmt = db()->prepare("SELECT COUNT(*) FROM contact_messages $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / MSG_PER_PAGE));
$page = min($page, $pages);
$stmt = db()->prepare("SELECT id, name, email, company, subject, message, status, created_at FROM contact_messages $whereSql ORDER BY created_at DESC, id DESC LIMIT " . MSG_PER_PAGE . ' OFFSET ' . (($page - 1) * MSG_PER_PAGE));
$stmt->execute($params);
$rows = $stmt->fetchAll();
$unreadTotal = (int) db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();

$exportForm = '<form method="post" action="/admin/messages" class="inline">' . csrf_field()
    . '<input type="hidden" name="q" value="' . e($f['q']) . '"><input type="hidden" name="show" value="' . e($f['show']) . '">'
    . '<button class="btn btn--ghost" name="action" value="export">' . icon_svg('file', 18) . '<span>Export to CSV</span></button></form>';
admin_page_start('Messages', 'messages', $admin, $exportForm);
?>
  <p class="lead"><?= $unreadTotal ?> unread message<?= $unreadTotal === 1 ? '' : 's' ?>. Messages from the website’s contact form appear here.</p>

  <form class="toolbar" method="get" action="/admin/messages" role="search">
    <label class="visually-hidden" for="msg-q">Search</label>
    <span class="toolbar__search"><?= icon_svg('search', 18) ?><input id="msg-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search name, email, subject or text…"></span>
    <label class="visually-hidden" for="msg-show">Show</label>
    <select id="msg-show" name="show" data-autosubmit>
      <option value="">All messages</option>
      <option value="unread"<?= $f['show'] === 'unread' ? ' selected' : '' ?>>Unread only</option>
      <option value="read"<?= $f['show'] === 'read' ? ' selected' : '' ?>>Read only</option>
    </select>
    <button class="btn btn--ghost btn--small" type="submit">Filter</button>
    <?php if ($f['q'] !== '' || $f['show'] !== ''): ?><a class="btn btn--text btn--small" href="/admin/messages">Clear</a><?php endif; ?>
  </form>

<?php if (!$rows): ?>
  <section class="card empty">
    <div class="empty__icon"><?= icon_svg('mail', 28) ?></div>
    <h2><?= $f['q'] !== '' || $f['show'] !== '' ? 'No messages match' : 'No messages yet' ?></h2>
    <p class="muted"><?= $f['q'] !== '' || $f['show'] !== '' ? 'Try a different word or clear the filter.' : 'When visitors use the contact form, their messages will appear here.' ?></p>
  </section>
<?php else: ?>
  <form method="post" action="/admin/messages" data-bulk>
    <?= csrf_field() ?>
    <input type="hidden" name="q" value="<?= e($f['q']) ?>"><input type="hidden" name="show" value="<?= e($f['show']) ?>">
    <div class="bulk-bar">
      <label class="check"><input type="checkbox" data-check-all><span>Select all</span></label>
      <button class="btn btn--small btn--ghost" name="action" value="mark_read">Mark read</button>
      <button class="btn btn--small btn--ghost" name="action" value="mark_unread">Mark unread</button>
      <button class="btn btn--small btn--danger-text" name="action" value="delete"><?= icon_svg('trash', 16) ?><span>Delete</span></button>
      <span class="muted small"><?= $total ?> message<?= $total === 1 ? '' : 's' ?></span>
    </div>
    <ul class="inbox">
      <?php foreach ($rows as $m): $unread = $m['status'] === 'new'; ?>
        <li class="inbox__item<?= $unread ? ' is-unread' : '' ?>">
          <label class="inbox__check"><input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>"><span class="visually-hidden">Select message from <?= e($m['name']) ?></span></label>
          <a class="inbox__link" href="/admin/messages?id=<?= (int) $m['id'] ?>">
            <span class="inbox__from"><?php if ($unread): ?><span class="dot" aria-hidden="true"></span><span class="visually-hidden">Unread: </span><?php endif; ?><?= e($m['name']) ?><?= $m['company'] ? ' <span class="muted">· ' . e($m['company']) . '</span>' : '' ?></span>
            <span class="inbox__subject"><?= e($m['subject'] ?: '(no subject)') ?> <span class="muted">— <?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $m['message']) ?? '', 0, 110, '…')) ?></span></span>
            <time class="inbox__date" datetime="<?= e(date('c', strtotime((string) $m['created_at']))) ?>"><?= e(date(date('Y') === date('Y', strtotime((string) $m['created_at'])) ? 'j M, g:i a' : 'j M Y', strtotime((string) $m['created_at']))) ?></time>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </form>
  <?= pagination_html_msg($page, $pages, $f) ?>
<?php endif;
admin_page_end();

// ---------------------------------------------------------------------------

function pagination_html_msg(int $page, int $pages, array $f): string
{
    if ($pages <= 1) {
        return '';
    }
    return '<nav class="pagination" aria-label="Pages">'
        . ($page > 1 ? '<a href="' . e(msg_list_url($f, ['page' => $page - 1 > 1 ? $page - 1 : null])) . '">← Previous</a>' : '<span class="is-disabled">← Previous</span>')
        . '<span>Page ' . $page . ' of ' . $pages . '</span>'
        . ($page < $pages ? '<a href="' . e(msg_list_url($f, ['page' => $page + 1])) . '">Next →</a>' : '<span class="is-disabled">Next →</span>')
        . '</nav>';
}

function msg_confirm_delete_page(array $admin, array $ids, array $f): void
{
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id, name, subject FROM contact_messages WHERE id IN ($in) ORDER BY created_at DESC");
    $stmt->execute($ids);
    $list = $stmt->fetchAll();
    admin_page_start('Delete messages', 'messages', $admin);
    ?>
  <section class="card card--narrow confirm">
    <h2>Delete <?= count($list) === 1 ? 'this message' : count($list) . ' messages' ?>?</h2>
    <ul class="usage-list">
      <?php foreach (array_slice($list, 0, 10) as $m): ?><li><?= e($m['name']) ?> — <?= e($m['subject'] ?: '(no subject)') ?></li><?php endforeach; ?>
      <?php if (count($list) > 10): ?><li>…and <?= count($list) - 10 ?> more</li><?php endif; ?>
    </ul>
    <p>Deleted messages are removed permanently. <strong>This cannot be undone.</strong> Tip: export to CSV first if you want a copy.</p>
    <form method="post" action="/admin/messages" class="confirm__buttons">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete"><input type="hidden" name="confirmed" value="1">
      <input type="hidden" name="q" value="<?= e($f['q']) ?>"><input type="hidden" name="show" value="<?= e($f['show']) ?>">
      <?php foreach ($list as $m): ?><input type="hidden" name="ids[]" value="<?= (int) $m['id'] ?>"><?php endforeach; ?>
      <button type="submit" class="btn btn--danger"><?= icon_svg('trash', 18) ?><span>Yes, delete</span></button>
      <a class="btn btn--ghost" href="<?= e(msg_list_url($f)) ?>">No, keep <?= count($list) === 1 ? 'it' : 'them' ?></a>
    </form>
  </section>
<?php
    admin_page_end();
}
