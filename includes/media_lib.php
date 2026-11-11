<?php
/**
 * Media Library: safe uploads, image sizes, public URLs, usage checks.
 *
 * Files live OUTSIDE the website in private/uploads/<2 chars>/<random>…
 *   Images -> re-encoded to WebP in three sizes (EXIF/location data removed):
 *               <random>.webp      large  (max 1920 px)
 *               <random>-md.webp   medium (max 1024 px)
 *               <random>-sm.webp   thumbnail (max 400 px)
 *   PDFs   -> <random>.pdf
 * Public address: /media/<id>/<name>.webp  (/media/<id>/md/…, /media/<id>/sm/…)
 */
declare(strict_types=1);

const MEDIA_SIZES = [
    'lg' => ['suffix' => '',    'max' => 1920, 'quality' => 82],
    'md' => ['suffix' => '-md', 'max' => 1024, 'quality' => 80],
    'sm' => ['suffix' => '-sm', 'max' => 400,  'quality' => 78],
];
const MEDIA_MAX_PIXELS = 40_000_000; // 40 megapixels (protects server memory)
const MEDIA_TYPES = [
    'image/jpeg'      => 'image',
    'image/png'       => 'image',
    'image/webp'      => 'image',
    'application/pdf' => 'document',
];

function media_max_bytes(): int
{
    return (int) config('uploads.max_bytes', 8 * 1024 * 1024);
}

function media_upload_dir(): string
{
    return rtrim((string) config('uploads.path', PRIVATE_PATH . '/uploads'), '/');
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

/** Human title from a file name: "IMG_2041-site visit.jpg" -> "IMG 2041 site visit". */
function media_title_from_filename(string $name): string
{
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = trim(preg_replace('/[_\-\s]+/', ' ', $base) ?? '');
    return mb_substr($base !== '' ? $base : 'File', 0, 190);
}

function media_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM media WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function media_ext(array $m): string
{
    return $m['kind'] === 'document' ? 'pdf' : ($m['mime_type'] === 'image/jpeg' ? 'jpg' : 'webp');
}

function media_slug(array $m): string
{
    $slug = slugify((string) ($m['title'] ?: $m['original_name']), 80);
    return $slug !== '' ? $slug : 'file';
}

/** Public URL, e.g. /media/12/md/site-visit.webp */
function media_url(array $m, string $size = 'lg'): string
{
    $size = $m['kind'] === 'image' && isset(MEDIA_SIZES[$size]) ? $size : 'lg';
    return '/media/' . (int) $m['id'] . '/' . ($size === 'lg' ? '' : $size . '/') . media_slug($m) . '.' . media_ext($m);
}

/** Absolute path of the stored file for a size. */
function media_path(array $m, string $size = 'lg'): string
{
    $suffix = $m['kind'] === 'image' ? (MEDIA_SIZES[$size]['suffix'] ?? '') : '';
    return media_upload_dir() . '/' . $m['file_name'] . $suffix . '.' . media_ext($m);
}

/** Data the admin scripts need (picker, uploader). */
function media_to_array(array $m): array
{
    return [
        'id'     => (int) $m['id'],
        'kind'   => $m['kind'],
        'title'  => (string) ($m['title'] ?: $m['original_name']),
        'alt'    => (string) $m['alt_text'],
        'url'    => media_url($m),
        'medium' => media_url($m, 'md'),
        'thumb'  => media_url($m, 'sm'),
        'width'  => $m['width'] ? (int) $m['width'] : null,
        'height' => $m['height'] ? (int) $m['height'] : null,
        'size'   => format_bytes((int) $m['size_bytes']),
        'edit'   => '/admin/media?action=edit&id=' . (int) $m['id'],
    ];
}

// ---------------------------------------------------------------------------
// Upload
// ---------------------------------------------------------------------------

/**
 * Validate and store one uploaded file.
 * Returns ['ok' => true, 'media' => row] or ['ok' => false, 'error' => message].
 */
function media_handle_upload(array $file, string $alt, string $title, ?int $userId): array
{
    $name = mb_substr(basename((string) ($file['name'] ?? 'file')), 0, 255);
    $fail = static fn (string $msg): array => ['ok' => false, 'error' => $name . ': ' . $msg];

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return $fail('the file is too large (maximum ' . format_bytes(media_max_bytes()) . ').');
    }
    if ($err === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Please choose a file to upload.'];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        return $fail('the upload did not complete. Please try again.');
    }
    $tmp  = (string) $file['tmp_name'];
    $size = (int) filesize($tmp);
    if ($size <= 0) {
        return $fail('the file is empty.');
    }
    if ($size > media_max_bytes()) {
        return $fail('the file is too large (' . format_bytes($size) . '). The maximum is ' . format_bytes(media_max_bytes()) . '.');
    }

    // The REAL file type, read from the file's contents (never trust the name).
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    if (!isset(MEDIA_TYPES[$mime])) {
        return $fail('this is not a real JPG, PNG, WebP image or PDF file, so it was rejected.');
    }
    $kind  = MEDIA_TYPES[$mime];
    $alt   = trim(preg_replace('/\s+/', ' ', $alt) ?? '');
    $title = trim($title) !== '' ? mb_substr(trim($title), 0, 190) : media_title_from_filename($name);

    if ($kind === 'document') {
        $alt = ''; // alt text only applies to images
    }
    if ($kind === 'image' && mb_strlen($alt) < 3) {
        return $fail('please describe the image in the "Alt text" box (needed for Google and screen readers).');
    }
    if (mb_strlen($alt) > 255) {
        return $fail('the alt text is too long (maximum 255 characters).');
    }

    $base = bin2hex(random_bytes(16));
    $fileName = substr($base, 0, 2) . '/' . $base;
    $dir = media_upload_dir() . '/' . substr($base, 0, 2);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        error_log('[majidtraders] Cannot create upload folder ' . $dir);
        return $fail('the server could not save the file (upload folder missing). Please check that private/uploads exists.');
    }

    if ($kind === 'document') {
        $fh = fopen($tmp, 'rb');
        $head = $fh ? (string) fread($fh, 5) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($head !== '%PDF-') {
            return $fail('this is not a valid PDF file.');
        }
        $dest = media_upload_dir() . '/' . $fileName . '.pdf';
        if (!move_uploaded_file($tmp, $dest)) {
            return $fail('the server could not save the file.');
        }
        @chmod($dest, 0640);
        $row = ['file_name' => $fileName, 'mime' => 'application/pdf', 'size' => $size, 'w' => null, 'h' => null];
    } else {
        $result = media_store_image($tmp, $mime, $fileName);
        if (isset($result['error'])) {
            return $fail($result['error']);
        }
        $row = $result;
    }

    $stmt = db()->prepare('INSERT INTO media (file_name, original_name, title, mime_type, kind, size_bytes, width, height, alt_text, uploaded_by)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$row['file_name'], $name, $title, $row['mime'], $kind, $row['size'], $row['w'], $row['h'], $alt !== '' ? $alt : null, $userId]);
    return ['ok' => true, 'media' => media_find((int) db()->lastInsertId())];
}

/** Decode, fix rotation, re-encode as WebP in 3 sizes. Returns file info or ['error' => …]. */
function media_store_image(string $tmp, string $mime, string $fileName): array
{
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1) {
        return ['error' => 'the image could not be read. It may be damaged.'];
    }
    if ($info[0] * $info[1] > MEDIA_MAX_PIXELS) {
        return ['error' => 'the image is too large in pixels (' . $info[0] . '×' . $info[1] . '). Please resize it below 40 megapixels.'];
    }
    @ini_set('memory_limit', '512M');

    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/webp' => @imagecreatefromwebp($tmp),
        default      => false,
    };
    if (!$img) {
        return ['error' => 'the image could not be opened (animated or damaged images are not supported).'];
    }
    if (!imageistruecolor($img)) {
        imagepalettetotruecolor($img);
    }

    // Phones store photos sideways and record the rotation in EXIF. Apply it,
    // because re-encoding removes all EXIF data (including GPS location).
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $img = match ($orientation) {
            2 => media_flip($img, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($img, 180, 0),
            4 => media_flip($img, IMG_FLIP_VERTICAL),
            5 => media_flip(imagerotate($img, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($img, -90, 0),
            7 => media_flip(imagerotate($img, 90, 0), IMG_FLIP_HORIZONTAL),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    $useWebp = function_exists('imagewebp');
    $ext     = $useWebp ? 'webp' : 'jpg';
    $srcW = imagesx($img);
    $srcH = imagesy($img);
    $large = null;

    foreach (MEDIA_SIZES as $key => $s) {
        $scale = min(1, $s['max'] / max($srcW, $srcH));
        $w = max(1, (int) round($srcW * $scale));
        $h = max(1, (int) round($srcH * $scale));
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        if (!$useWebp) { // JPEG has no transparency: use white
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagealphablending($out, true);
        }
        imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
        $path = media_upload_dir() . '/' . $fileName . $s['suffix'] . '.' . $ext;
        $ok = $useWebp ? imagewebp($out, $path, $s['quality']) : imagejpeg($out, $path, $s['quality']);
        imagedestroy($out);
        if (!$ok) {
            imagedestroy($img);
            media_delete_files(['file_name' => $fileName, 'kind' => 'image', 'mime_type' => $useWebp ? 'image/webp' : 'image/jpeg']);
            return ['error' => 'the server could not save the image.'];
        }
        @chmod($path, 0640);
        if ($key === 'lg') {
            $large = ['w' => $w, 'h' => $h, 'size' => (int) filesize($path)];
        }
    }
    imagedestroy($img);

    return ['file_name' => $fileName, 'mime' => $useWebp ? 'image/webp' : 'image/jpeg'] + $large;
}

function media_flip(GdImage $img, int $mode): GdImage
{
    imageflip($img, $mode);
    return $img;
}

// ---------------------------------------------------------------------------
// Where is a file used?
// ---------------------------------------------------------------------------

/** List of places that use this media item: [['label' => …, 'url' => …], …] */
function media_usage(int $id): array
{
    $pdo = db();
    $found = [];

    // [table, column, name column, label, admin page]
    $direct = [
        ['services',       'image_id',       'title',       'Service (cover image)',          'services'],
        ['projects',       'cover_image_id', 'title',       'Project (cover image)',          'projects'],
        ['clients',        'logo_id',        'name',        'Client (logo)',                  'clients'],
        ['team_members',   'photo_id',       'name',        'Team member (photo)',            'team'],
        ['blog_posts',     'cover_image_id', 'title',       'Blog post (cover image)',        'blog'],
        ['certifications', 'image_id',       'title',       'Certification (image)',          'certifications'],
        ['certifications', 'document_id',    'title',       'Certification (document)',       'certifications'],
        ['testimonials',   'photo_id',       'author_name', 'Testimonial (photo)',            'testimonials'],
    ];
    foreach ($direct as [$table, $col, $nameCol, $label, $page]) {
        $stmt = $pdo->prepare("SELECT id, `$nameCol` AS name FROM `$table` WHERE `$col` = ?");
        $stmt->execute([$id]);
        foreach ($stmt as $r) {
            $found[] = ['label' => $label . ': ' . $r['name'], 'url' => "/admin/$page?action=edit&id=" . $r['id']];
        }
    }

    $galleries = [
        ['service_images', 'service_id', 'services', 'title', 'Service gallery', 'services'],
        ['project_images', 'project_id', 'projects', 'title', 'Project gallery', 'projects'],
    ];
    foreach ($galleries as [$link, $fk, $table, $nameCol, $label, $page]) {
        $stmt = $pdo->prepare("SELECT t.id, t.`$nameCol` AS name FROM `$link` l JOIN `$table` t ON t.id = l.`$fk` WHERE l.media_id = ?");
        $stmt->execute([$id]);
        foreach ($stmt as $r) {
            $found[] = ['label' => $label . ': ' . $r['name'], 'url' => "/admin/$page?action=edit&id=" . $r['id']];
        }
    }

    // Images placed inside long descriptions (rich text).
    $texts = [['services', 'title', 'services'], ['projects', 'title', 'projects'], ['blog_posts', 'title', 'blog']];
    foreach ($texts as [$table, $nameCol, $page]) {
        $stmt = $pdo->prepare("SELECT id, `$nameCol` AS name FROM `$table` WHERE body LIKE ?");
        $stmt->execute(['%/media/' . $id . '/%']);
        foreach ($stmt as $r) {
            $found[] = ['label' => 'Inside the text of: ' . $r['name'], 'url' => "/admin/$page?action=edit&id=" . $r['id']];
        }
    }

    require_once __DIR__ . '/settings.php';
    $keys = media_setting_keys();
    $stmt = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key IN (' . implode(',', array_fill(0, count($keys), '?')) . ') AND setting_value = ?');
    $stmt->execute([...array_keys($keys), (string) $id]);
    foreach ($stmt as $r) {
        $found[] = ['label' => $keys[$r['setting_key']], 'url' => '/admin/settings'];
    }

    return $found;
}

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

function media_delete_files(array $m): void
{
    if (($m['kind'] ?? '') === 'image') {
        foreach (array_keys(MEDIA_SIZES) as $size) {
            @unlink(media_path($m, $size));
        }
    } else {
        @unlink(media_path($m));
    }
}

/** Delete a media item: the files, the database row and any settings pointing to it. */
function media_delete(int $id): void
{
    $m = media_find($id);
    if (!$m) {
        return;
    }
    require_once __DIR__ . '/settings.php';
    $keys = array_keys(media_setting_keys());
    $stmt = db()->prepare("UPDATE settings SET setting_value = '' WHERE setting_key IN (" . implode(',', array_fill(0, count($keys), '?')) . ') AND setting_value = ?');
    $stmt->execute([...$keys, (string) $id]);
    // Cover images etc. are cleared automatically (ON DELETE SET NULL); gallery links are removed (CASCADE).
    db()->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    media_delete_files($m);
}
