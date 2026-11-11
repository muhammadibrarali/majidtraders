<?php
/**
 * Reusable admin form fields.
 *
 * Every field: label, optional help text, inline error message, and the
 * right attributes for the browser scripts (character counter, rich text,
 * media picker, slug…). All values are escaped.
 *
 *   $errors = ['title' => 'Please enter a title.'];
 *   echo field_text('title', 'Title', $value, ['required' => true, 'help' => '…'], $errors);
 */
declare(strict_types=1);

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/media_lib.php';

/** Shared parts: opening wrapper + label + help, and closing error. */
function field_open(string $name, string $label, array $opt, array $errors, string $extraClass = ''): string
{
    $id   = 'f-' . $name;
    $req  = !empty($opt['required']) ? ' <span class="req" aria-hidden="true">*</span><span class="visually-hidden">(required)</span>' : '';
    $html = '<div class="field' . ($extraClass ? ' ' . $extraClass : '') . (isset($errors[$name]) ? ' has-error' : '') . '">';
    $html .= '<label for="' . e($id) . '">' . e($label) . $req . '</label>';
    if (!empty($opt['help'])) {
        $html .= '<p class="help" id="' . e($id) . '-help">' . e($opt['help']) . '</p>';
    }
    return $html;
}

function field_close(string $name, array $errors): string
{
    return (isset($errors[$name]) ? '<p class="field-error" id="f-' . e($name) . '-error">' . e($errors[$name]) . '</p>' : '') . '</div>';
}

/** aria-describedby + aria-invalid attributes for the input. */
function field_aria(string $name, array $opt, array $errors): string
{
    $ids = [];
    if (!empty($opt['help'])) {
        $ids[] = 'f-' . $name . '-help';
    }
    if (isset($errors[$name])) {
        $ids[] = 'f-' . $name . '-error';
    }
    return ($ids ? ' aria-describedby="' . e(implode(' ', $ids)) . '"' : '') . (isset($errors[$name]) ? ' aria-invalid="true"' : '');
}

/** Character counter attributes: data-counter + optional recommended range. */
function field_counter_attrs(array $opt): string
{
    if (empty($opt['counter'])) {
        return '';
    }
    $a = ' data-counter';
    if (!empty($opt['recommend'])) {
        $a .= ' data-recommend="' . e($opt['recommend'][0] . '-' . $opt['recommend'][1]) . '"';
    }
    return $a;
}

/** Single-line text (also type email / url / tel / number via $opt['type']). */
function field_text(string $name, string $label, ?string $value, array $opt = [], array $errors = []): string
{
    $type = $opt['type'] ?? 'text';
    $html = field_open($name, $label, $opt, $errors);
    $html .= '<input id="f-' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '"'
        . (!empty($opt['required']) ? ' required' : '')
        . (isset($opt['max']) ? ' maxlength="' . (int) $opt['max'] . '"' : '')
        . (isset($opt['placeholder']) ? ' placeholder="' . e($opt['placeholder']) . '"' : '')
        . (isset($opt['inputmode']) ? ' inputmode="' . e($opt['inputmode']) . '"' : '')
        . (isset($opt['autocomplete']) ? ' autocomplete="' . e($opt['autocomplete']) . '"' : '')
        . ($type === 'number' ? ' step="1"' : '')
        . (isset($opt['min']) ? ' min="' . (int) $opt['min'] . '"' : '')
        . (isset($opt['data']) ? ' ' . $opt['data'] : '')
        . field_counter_attrs($opt) . field_aria($name, $opt, $errors) . '>';
    return $html . field_close($name, $errors);
}

function field_textarea(string $name, string $label, ?string $value, array $opt = [], array $errors = []): string
{
    $html = field_open($name, $label, $opt, $errors);
    $html .= '<textarea id="f-' . e($name) . '" name="' . e($name) . '" rows="' . (int) ($opt['rows'] ?? 3) . '"'
        . (!empty($opt['required']) ? ' required' : '')
        . (isset($opt['max']) ? ' maxlength="' . (int) $opt['max'] . '"' : '')
        . (isset($opt['placeholder']) ? ' placeholder="' . e($opt['placeholder']) . '"' : '')
        . field_counter_attrs($opt) . field_aria($name, $opt, $errors) . '>' . e($value) . '</textarea>';
    return $html . field_close($name, $errors);
}

/** Web-address part, filled automatically from another field until edited by hand. */
function field_slug(string $name, string $label, ?string $value, array $opt = [], array $errors = []): string
{
    $html = field_open($name, $label, $opt, $errors);
    $html .= '<div class="slug-input"><span class="slug-input__prefix">' . e($opt['prefix'] ?? '/') . '</span>'
        . '<input id="f-' . e($name) . '" name="' . e($name) . '" type="text" value="' . e($value) . '" maxlength="160"'
        . ' pattern="[a-z0-9]+(-[a-z0-9]+)*" autocapitalize="off" spellcheck="false"'
        . ' data-slug-from="f-' . e($opt['from'] ?? 'title') . '"' . ($value === '' || $value === null ? ' data-slug-auto' : '')
        . field_aria($name, $opt, $errors) . '></div>';
    return $html . field_close($name, $errors);
}

/** Rich text: a <textarea> that the browser script turns into a simple editor. */
function field_richtext(string $name, string $label, ?string $value, array $opt = [], array $errors = []): string
{
    $html = field_open($name, $label, $opt, $errors, 'field--rich');
    $html .= '<textarea id="f-' . e($name) . '" name="' . e($name) . '" rows="12" data-richtext'
        . field_aria($name, $opt, $errors) . '>' . e($value) . '</textarea>';
    return $html . field_close($name, $errors);
}

function field_select(string $name, string $label, ?string $value, array $options, array $opt = [], array $errors = []): string
{
    $html = field_open($name, $label, $opt, $errors);
    $html .= '<select id="f-' . e($name) . '" name="' . e($name) . '"' . field_aria($name, $opt, $errors) . '>';
    foreach ($options as $k => $v) {
        $html .= '<option value="' . e((string) $k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $html . '</select>' . field_close($name, $errors);
}

/** Pick one icon from the icon set (radio buttons showing the icons). */
function field_icon(string $name, string $label, ?string $value, array $opt = [], array $errors = []): string
{
    $html = '<fieldset class="field icon-field' . (isset($errors[$name]) ? ' has-error' : '') . '"><legend>' . e($label) . '</legend>';
    if (!empty($opt['help'])) {
        $html .= '<p class="help">' . e($opt['help']) . '</p>';
    }
    $html .= '<div class="icon-choices">';
    $html .= '<label class="icon-choice"><input type="radio" name="' . e($name) . '" value=""' . ($value ? '' : ' checked') . '><span class="icon-choice__box">' . icon_svg('close') . '<small>None</small></span></label>';
    foreach (service_icon_choices() as $key => $text) {
        $html .= '<label class="icon-choice" title="' . e($text) . '"><input type="radio" name="' . e($name) . '" value="' . e($key) . '"'
            . ($value === $key ? ' checked' : '') . '><span class="icon-choice__box">' . icon_svg($key, 26) . '<small>' . e(explode(' (', explode(' / ', $text)[0])[0]) . '</small></span></label>';
    }
    return $html . '</div>' . (isset($errors[$name]) ? '<p class="field-error">' . e($errors[$name]) . '</p>' : '') . '</fieldset>';
}

/** On/off switch (checkbox). */
function field_switch(string $name, string $label, bool $checked, array $opt = []): string
{
    return '<div class="field field--switch"><label class="switch"><input type="checkbox" name="' . e($name) . '" value="1"'
        . ($checked ? ' checked' : '') . (isset($opt['data']) ? ' ' . $opt['data'] : '') . '><span class="switch__track" aria-hidden="true"></span><span class="switch__label">' . e($label) . '</span></label>'
        . (!empty($opt['help']) ? '<p class="help">' . e($opt['help']) . '</p>' : '') . '</div>';
}

/** Draft / Published switch. */
function field_status(string $value): string
{
    return '<div class="field field--status">'
        . '<span class="field__label">Status</span>'
        . '<div class="segmented" role="radiogroup" aria-label="Status">'
        . '<label><input type="radio" name="status" value="draft"' . ($value !== 'published' ? ' checked' : '') . '><span>Draft</span></label>'
        . '<label><input type="radio" name="status" value="published"' . ($value === 'published' ? ' checked' : '') . '><span>Published</span></label>'
        . '</div><p class="help">Only <strong>Published</strong> items appear on the website.</p></div>';
}

/**
 * Choose one file from the Media Library (or upload a new one).
 * $accept: 'image' or 'document'.
 */
function field_media(string $name, string $label, ?array $media, array $opt = [], array $errors = []): string
{
    $accept = $opt['accept'] ?? 'image';
    $html = field_open($name, $label, $opt, $errors, 'field--media');
    $html .= '<div class="media-field" data-media-field data-accept="' . e($accept) . '">'
        . '<input type="hidden" id="f-' . e($name) . '" name="' . e($name) . '" value="' . ($media ? (int) $media['id'] : '') . '">'
        . '<div class="media-field__preview" data-preview>' . ($media ? media_preview_html($media) : '<span class="muted">Nothing chosen</span>') . '</div>'
        . '<div class="media-field__buttons">'
        . '<button type="button" class="btn btn--small btn--ghost" data-media-choose>' . icon_svg('image', 18) . '<span>Choose from Media Library</span></button>'
        . '<button type="button" class="btn btn--small btn--ghost" data-media-upload>' . icon_svg('upload', 18) . '<span>Upload new</span></button>'
        . '<button type="button" class="btn btn--small btn--text" data-media-remove' . ($media ? '' : ' hidden') . '>Remove</button>'
        . '</div><noscript><p class="help">Please enable JavaScript to choose files.</p></noscript></div>';
    return $html . field_close($name, $errors);
}

/** Choose several images (ordered). Posts $name[] = media IDs. */
function field_gallery(string $name, string $label, array $items, array $opt = [], array $errors = []): string
{
    $html = field_open($name, $label, $opt, $errors, 'field--gallery');
    $html .= '<div class="gallery-field" data-gallery-field data-name="' . e($name) . '[]">'
        . '<ul class="gallery-field__list" data-gallery-list>';
    foreach ($items as $m) {
        $html .= gallery_item_html($name, $m);
    }
    $html .= '</ul><button type="button" class="btn btn--small btn--ghost" data-gallery-add>' . icon_svg('plus', 18) . '<span>Add images</span></button>'
        . '<noscript><p class="help">Please enable JavaScript to choose images.</p></noscript></div>';
    return $html . field_close($name, $errors);
}

function gallery_item_html(string $name, array $m): string
{
    return '<li class="gallery-item"><input type="hidden" name="' . e($name) . '[]" value="' . (int) $m['id'] . '">'
        . '<img src="' . e(media_url($m, 'sm')) . '" alt="' . e((string) $m['alt_text']) . '" loading="lazy">'
        . '<span class="gallery-item__tools">'
        . '<button type="button" data-gallery-move="-1" title="Move left">' . icon_svg('up', 16) . '<span class="visually-hidden">Move earlier</span></button>'
        . '<button type="button" data-gallery-move="1" title="Move right">' . icon_svg('down', 16) . '<span class="visually-hidden">Move later</span></button>'
        . '<button type="button" data-gallery-remove title="Remove">' . icon_svg('close', 16) . '<span class="visually-hidden">Remove</span></button>'
        . '</span></li>';
}

/** Small preview for a chosen media item. */
function media_preview_html(array $m): string
{
    if ($m['kind'] === 'image') {
        return '<img src="' . e(media_url($m, 'sm')) . '" alt="' . e((string) $m['alt_text']) . '"><span class="media-field__name">' . e($m['title'] ?: $m['original_name']) . '</span>';
    }
    return '<span class="file-badge">' . icon_svg('file', 28) . '<span>PDF</span></span><span class="media-field__name">' . e($m['title'] ?: $m['original_name']) . '</span>';
}

/** Box at the top of a form listing all problems. */
function form_error_summary(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $html = '<div class="notice notice--error" role="alert" tabindex="-1" data-error-summary><p><strong>Please fix ' . (count($errors) === 1 ? 'this problem' : 'these ' . count($errors) . ' problems') . ':</strong></p><ul>';
    foreach ($errors as $field => $msg) {
        $html .= '<li><a href="#f-' . e((string) $field) . '">' . e($msg) . '</a></li>';
    }
    return $html . '</ul></div>';
}

// ---------------------------------------------------------------------------
// Reading submitted values
// ---------------------------------------------------------------------------

function post_str(string $name, int $max = 0): string
{
    $v = $_POST[$name] ?? '';
    $v = is_string($v) ? trim(str_replace("\0", '', $v)) : '';
    return $max > 0 && mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
}

/** Media ID from a picker field; null if empty or not the right kind. */
function post_media_id(string $name, string $kind = 'image'): ?int
{
    $id = (int) ($_POST[$name] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $m = media_find($id);
    return $m && $m['kind'] === $kind ? $id : null;
}

/** Ordered list of image IDs from a gallery field. */
function post_gallery_ids(string $name, int $max = 40): array
{
    $ids = [];
    foreach ((array) ($_POST[$name] ?? []) as $v) {
        $id = (int) $v;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    $ids = array_slice($ids, 0, $max);
    if (!$ids) {
        return [];
    }
    $stmt = db()->prepare("SELECT id FROM media WHERE kind = 'image' AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')');
    $stmt->execute($ids);
    $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return array_values(array_filter($ids, static fn ($id) => in_array($id, $valid, true)));
}

/** Load media rows by IDs, keeping the given order. */
function media_rows(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $stmt = db()->prepare('SELECT * FROM media WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt as $r) {
        $byId[(int) $r['id']] = $r;
    }
    return array_values(array_filter(array_map(static fn ($id) => $byId[$id] ?? null, $ids)));
}

/** Is this a safe link for a button? (/page, #anchor, https://…, tel:, mailto:) */
function is_safe_link(string $url): bool
{
    return $url === ''
        || preg_match('#^/(?!/)[^\s]*$#', $url)
        || preg_match('#^\#[\w-]*$#', $url)
        || preg_match('#^(tel:\+?[\d\s-]{5,20}|mailto:[^\s@]+@[^\s@]+)$#i', $url)
        || (filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url));
}
